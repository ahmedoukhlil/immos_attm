<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    public function test_administrator_has_management_permissions(): void
    {
        $admin = new User(['role' => 'admin']);

        $this->assertTrue($admin->canPerform('immobilisations.manage'));
        $this->assertTrue($admin->canPerform('referentiel.manage'));
        $this->assertTrue($admin->canPerform('inventaires.manage'));
        $this->assertTrue($admin->canPerform('administration.manage'));
        $this->assertTrue($admin->canPerform('pwa.scan_assigned'));
    }

    public function test_agent_is_read_only_outside_assigned_physical_inventory(): void
    {
        $agent = new User(['role' => 'agent']);

        $this->assertTrue($agent->canPerform('immobilisations.view'));
        $this->assertTrue($agent->canPerform('localisations.view'));
        $this->assertTrue($agent->canPerform('inventaires.view'));
        $this->assertTrue($agent->canPerform('inventaires.export_assigned'));
        $this->assertTrue($agent->canPerform('pwa.scan_assigned'));
        $this->assertFalse($agent->canPerform('immobilisations.manage'));
        $this->assertFalse($agent->canPerform('referentiel.manage'));
        $this->assertFalse($agent->canPerform('inventaires.manage'));
        $this->assertFalse($agent->canPerform('administration.manage'));
    }

    public function test_manager_can_manage_operations_but_not_users(): void
    {
        $manager = new User(['role' => 'gestionnaire']);

        $this->assertTrue($manager->canPerform('dashboard.view'));
        $this->assertTrue($manager->canPerform('immobilisations.manage'));
        $this->assertTrue($manager->canPerform('referentiel.manage'));
        $this->assertTrue($manager->canPerform('inventaires.manage'));
        $this->assertTrue($manager->canPerform('pwa.scan_assigned'));
        $this->assertTrue($manager->canManageOperations());
        $this->assertFalse($manager->isAdmin());
        $this->assertFalse($manager->canPerform('administration.manage'));
    }

    public function test_unknown_role_or_ability_grants_no_access(): void
    {
        $unknown = new User(['role' => 'inconnu']);

        $this->assertFalse($unknown->canPerform('dashboard.view'));
        $this->assertFalse($unknown->canPerform('permission.inconnue'));
    }
}
