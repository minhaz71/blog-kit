<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Events\OrderStatusChanged;
use App\Filament\Resources\OrderResource;
use App\Models\EmailTemplate;
use App\Services\Email\EmailService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    /** Status the order had when the form was loaded, captured before save. */
    protected ?string $statusBefore = null;

    public function getTitle(): string
    {
        return 'Order '.$this->record->order_number;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('invoice')
                ->label('Download invoice')
                ->icon('heroicon-o-arrow-down-tray')
                // Public, code-signed link (source=admin) — works for admins,
                // unlike the account route which is scoped to the buyer.
                ->url(fn () => $this->record->invoiceUrl('admin'))
                ->openUrlInNewTab()
                ->visible(fn () => \Illuminate\Support\Facades\Route::has('invoice.download')),

            // Manually (re)send an order email — for when a mail client dropped
            // it, or to notify a specific person/department after an update.
            Action::make('resendEmail')
                ->label('Resend email')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->modalWidth('lg')
                ->form([
                    Select::make('template_key')
                        ->label('Which email')
                        ->options(fn () => EmailTemplate::where('is_active', true)->orderBy('name')->pluck('name', 'key'))
                        ->default('order_confirmed')
                        ->required()
                        ->native(false),
                    Select::make('audience')
                        ->label('Send to')
                        ->options([
                            'customer' => 'Customer',
                            'admin' => 'Admin / departments (notification recipients)',
                            'both' => 'Customer + admin',
                            'custom' => 'Custom email(s)',
                        ])
                        ->default('customer')
                        ->required()
                        ->live()
                        ->native(false),
                    TextInput::make('custom_email')
                        ->label('Custom email(s)')
                        ->placeholder('a@example.com, b@example.com')
                        ->visible(fn ($get) => $get('audience') === 'custom')
                        ->required(fn ($get) => $get('audience') === 'custom'),
                ])
                ->action(function (array $data): void {
                    $order = $this->record;
                    $adminTo = (string) (setting('emails.admin_recipient') ?: setting('emails.from_email') ?: config('mail.from.address'));

                    $targets = match ($data['audience']) {
                        'customer' => [$order->customer_email],
                        'admin' => [$adminTo],
                        'both' => [$order->customer_email, $adminTo],
                        'custom' => [$data['custom_email'] ?? ''],
                        default => [],
                    };

                    $to = EmailService::normalizeRecipients($targets);

                    if ($to === []) {
                        Notification::make()->title('No valid recipient to send to.')->warning()->send();

                        return;
                    }

                    $ok = app(EmailService::class)->sendOrderEmail($data['template_key'], $order, $to);

                    Notification::make()
                        ->title($ok ? 'Email resent to '.implode(', ', $to) : 'Email failed to send')
                        ->body($ok ? null : 'Check Admin → System → Email logs for the mail-server error.')
                        ->{$ok ? 'success' : 'danger'}()
                        ->send();
                }),
        ];
    }

    protected function beforeSave(): void
    {
        $this->statusBefore = $this->record->getOriginal('status');
    }

    protected function afterSave(): void
    {
        $order = $this->record;
        $newStatus = $order->status;

        // Log the transition + fire the same event storefront status changes use,
        // so notifications / stock handling stay consistent with the public flow.
        if ($this->statusBefore && $this->statusBefore !== $newStatus) {
            $order->statusHistories()->create([
                'from_status' => $this->statusBefore,
                'to_status' => $newStatus,
                'user_id' => auth()->id(),
            ]);

            if ($newStatus === 'completed' && ! $order->completed_at) {
                $order->forceFill(['completed_at' => now()])->saveQuietly();
            }

            event(new OrderStatusChanged($order, $this->statusBefore, $newStatus));
        }

        // Items are only editable while pending, so that is the only path that
        // can change line totals — recompute the order money columns from them.
        if ($this->statusBefore === 'pending' || $newStatus === 'pending') {
            $order->recalculateTotals();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
