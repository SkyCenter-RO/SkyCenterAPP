<?php

namespace App\Filament\Resources\MarketingReviews\Pages;

use App\Filament\Resources\MarketingReviews\MarketingReviewResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMarketingReview extends EditRecord
{
    protected static string $resource = MarketingReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
