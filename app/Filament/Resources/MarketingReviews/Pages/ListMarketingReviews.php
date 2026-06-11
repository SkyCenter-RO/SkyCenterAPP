<?php

namespace App\Filament\Resources\MarketingReviews\Pages;

use App\Filament\Resources\MarketingReviews\MarketingReviewResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMarketingReviews extends ListRecords
{
    protected static string $resource = MarketingReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
