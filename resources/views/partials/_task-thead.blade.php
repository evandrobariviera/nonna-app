{{-- Cabeçalho padrão da tabela de tarefas --}}
<thead>
    <tr>
        <th style="width:36px"><input type="checkbox" @click="toggleAll($event.target.checked)" title="Selecionar todos"></th>
        <th>Tarefa</th>
        <th style="width:150px">Cliente</th>
        <th style="width:150px">Projeto</th>
        <th style="width:44px; text-align:center">Resp.</th>
        <th style="width:44px; text-align:center">Exec.</th>
        <th style="width:100px">Dt. Aprv.</th>
        <th style="width:80px">Origem</th>
        <th style="width:120px">Destino</th>
        <th style="width:140px; padding-left:16px">Status</th>
        <th style="width:150px">Situação</th>
        <th style="width:140px"></th>
    </tr>
</thead>
