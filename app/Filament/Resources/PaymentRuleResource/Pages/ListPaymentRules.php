<?php

namespace App\Filament\Resources\PaymentRuleResource\Pages;

use App\Filament\Resources\PaymentRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPaymentRules extends ListRecords
{
    protected static string $resource = PaymentRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
