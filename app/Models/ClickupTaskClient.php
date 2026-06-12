<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClickupTaskClient extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'clickup_tasks_clients';
    protected $primaryKey = 'client_task_id';

    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    protected $fillable = [
        'client_task_id',
        'company_name',
        'website',
        'cnpj',
        'last_synced_at',
    ];
}
