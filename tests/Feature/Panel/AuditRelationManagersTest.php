<?php

namespace Tests\Feature\Panel;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditRelationManagersTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function auditRelationManagers(): array
    {
        return [
            'parking status audits' => ['app/Filament/Resources/ParkingReservations/RelationManagers/StatusAuditsRelationManager.php'],
            'payment change audits' => ['app/Filament/Resources/Payments/RelationManagers/ChangeAuditsRelationManager.php'],
            'automation events' => ['app/Filament/Resources/AutomationWebhookLogs/RelationManagers/EventsRelationManager.php'],
        ];
    }

    #[DataProvider('auditRelationManagers')]
    public function test_audit_relation_managers_do_not_expose_mutating_actions(string $path): void
    {
        $contents = file_get_contents(base_path($path));

        foreach ([
            'CreateAction::make(',
            'AssociateAction::make(',
            'EditAction::make(',
            'DissociateAction::make(',
            'DeleteAction::make(',
            'DissociateBulkAction::make(',
            'DeleteBulkAction::make(',
        ] as $action) {
            $this->assertStringNotContainsString($action, $contents, "{$path} exposes {$action}");
        }
    }
}
