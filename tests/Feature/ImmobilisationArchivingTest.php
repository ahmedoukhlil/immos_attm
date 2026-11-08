<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\HistoriqueTransfert;
use App\Models\Immobilisation;
use Tests\Concerns\CreatesLaxSchema;
use Tests\TestCase;

class ImmobilisationArchivingTest extends TestCase
{
    use CreatesLaxSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createLaxSchema();
    }

    public function test_archiving_a_bien_keeps_it_and_its_transfer_history(): void
    {
        $bien = new Immobilisation;
        $bien->id = 42;
        $bien->save();

        HistoriqueTransfert::query()->create([
            'immobilisation_id' => 42,
            'date_transfert' => now(),
        ]);

        $bien->delete();

        $this->assertNull(Immobilisation::find(42));
        $this->assertSame(42, Immobilisation::withTrashed()->findOrFail(42)->id);
        $this->assertDatabaseHas('historique_transferts', ['immobilisation_id' => 42]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'archived',
            'auditable_type' => Immobilisation::class,
            'auditable_id' => '42',
        ]);
    }

    public function test_an_archived_bien_can_be_restored_with_its_history(): void
    {
        $bien = new Immobilisation;
        $bien->id = 43;
        $bien->save();

        HistoriqueTransfert::query()->create([
            'immobilisation_id' => 43,
            'date_transfert' => now(),
        ]);

        $bien->delete();
        $bien->restore();

        $this->assertSame(43, Immobilisation::findOrFail(43)->id);
        $this->assertDatabaseHas('historique_transferts', ['immobilisation_id' => 43]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'restored',
            'auditable_type' => Immobilisation::class,
            'auditable_id' => '43',
        ]);
    }

    public function test_asset_changes_are_recorded_with_old_and_new_values(): void
    {
        $bien = new Immobilisation;
        $bien->id = 44;
        $bien->autres_informations = 'Ancienne observation';
        $bien->save();

        $bien->update(['autres_informations' => 'Nouvelle observation']);

        $log = AuditLog::query()
            ->where('event', 'updated')
            ->where('auditable_id', '44')
            ->sole();

        $this->assertSame('Ancienne observation', $log->old_values['autres_informations']);
        $this->assertSame('Nouvelle observation', $log->new_values['autres_informations']);
    }
}
