<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $company
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $logo_path
 * @property-read string|null $logo_url
 * @property string $status
 */
#[Fillable(['name', 'company', 'email', 'phone', 'address', 'logo_path', 'status'])]
#[Hidden(['logo_path'])]
class Client extends Model
{
    protected function logoUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->logo_path === null
                ? null
                : route('management.clientes.logo', [
                    'cliente' => $this->getRouteKey(),
                    'v' => $this->updated_at?->timestamp,
                ]),
        );
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return HasMany<FinancialDocument, $this> */
    public function financialDocuments(): HasMany
    {
        return $this->hasMany(FinancialDocument::class);
    }
}
