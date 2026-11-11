<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** Item dentro de um pacote aberto do catálogo (pivot com id uuid). */
class CatalogPackageItem extends Pivot
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'catalog_package_items';

    public $incrementing = false;

    protected $keyType = 'string';
}
