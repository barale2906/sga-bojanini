<?php

namespace Tests\Feature\Assets;

use App\Modules\Assets\Infrastructure\Persistence\Models\AssetCategoryModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetPlaceModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetSiteModel;
use App\Modules\Auth\Application\DTOs\SaveSignatureData;
use App\Modules\Auth\Application\UseCases\SaveMySignatureUseCase;
use App\Modules\Auth\Domain\ValueObjects\IdentityDocumentType;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Base de las pruebas del módulo de activos: siembra permisos y categorías,
 * y ofrece ayudas para crear sedes/sitios, activos y firmas.
 */
abstract class AssetTestCase extends TestCase
{
    use RefreshDatabase;

    protected const SIGNATURE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected UserModel $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\AssetSeeder']);

        $this->admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->firstOrFail();
        RateLimiter::clear('signature-pin:' . $this->admin->id);
    }

    protected function auth(?UserModel $user = null): array
    {
        $user ??= $this->admin;
        Auth::forgetGuards();

        return ['Authorization' => 'Bearer ' . $user->createToken('test', $user->getAllPermissions()->pluck('name')->all())->plainTextToken];
    }

    /** Usuario sin roles con solo los permisos indicados. */
    protected function userWith(array $permissions, string $email = 'activos@test.local'): UserModel
    {
        $user = UserModel::create([
            'name'      => 'Usuario Activos',
            'email'     => $email,
            'password'  => bcrypt('Test1234!'),
            'is_active' => true,
        ]);
        $user->givePermissionTo($permissions);

        return $user;
    }

    protected function category(string $code): AssetCategoryModel
    {
        return AssetCategoryModel::where('code', $code)->firstOrFail();
    }

    protected function place(string $site = 'Clínica', string $place = 'Consultorio 1'): AssetPlaceModel
    {
        $siteModel = AssetSiteModel::firstOrCreate(['name' => $site], ['is_active' => true]);

        return AssetPlaceModel::firstOrCreate(['site_id' => $siteModel->id, 'name' => $place], ['is_active' => true]);
    }

    /** Registra un activo por API y devuelve su `data`. */
    protected function createAsset(string $categoryCode = 'BIO', array $overrides = []): array
    {
        return $this->withHeaders($this->auth())
            ->postJson('/api/v1/assets', array_merge([
                'name'        => 'Monitor de signos vitales',
                'category_id' => $this->category($categoryCode)->id,
                'place_id'    => $this->place()->id,
                'serial'      => 'SN-' . uniqid(),
            ], $overrides))
            ->assertCreated()
            ->json('data');
    }

    /** Registra la firma de un usuario y devuelve su PIN. */
    protected function registerSignature(UserModel $user, string $document): string
    {
        return app(SaveMySignatureUseCase::class)->execute($user->id, new SaveSignatureData(
            documentType:   IdentityDocumentType::CC,
            documentNumber: $document,
            signatureData:  self::SIGNATURE,
        ))['pin'];
    }

    protected function padSigner(string $name = 'Técnico Externo'): array
    {
        return ['name' => $name, 'document' => 'CC 99887766', 'signature' => self::SIGNATURE];
    }
}
