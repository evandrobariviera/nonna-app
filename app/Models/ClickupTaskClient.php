<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClickupTaskClient extends Model
{
    //
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClickupTaskClient extends Model
{
    // 1. Diz ao Laravel o nome exato da sua tabela existente no Postgres
    protected $table = 'clickup_tasks_clients';

    // 2. Define a chave primária (que no seu banco é string, não id inteiro)
    protected $primaryKey = 'client_task_id';

    // 3. Informa que a chave primária não é um número autoincrementável
    public $incrementing = false;
    protected $keyType = 'string';

    // 4. Desabilita as colunas padrão de timestamp do Laravel (created_at/updated_at) 
    // já que o seu banco controla isso pela última sincronização
    public $timestamps = false;

    // 5. Campos que podem ser preenchidos em massa
    protected $fillable = [
        'client_task_id',
        'company_name',
        'website',
        'cnpj',
        'last_synced_at'
    ];
}