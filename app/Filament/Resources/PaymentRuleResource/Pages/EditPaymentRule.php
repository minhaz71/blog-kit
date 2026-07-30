<?php

namespace App\Filament\Resources\PaymentRuleResource\Pages;

use App\Filament\Resources\PaymentRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPaymentRule extends EditRecord
{
    protected static string $resource = PaymentRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
