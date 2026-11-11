<?php

// Regras comerciais das Propostas (Central de Comércio). Ver ProposalCalculator.
return [
    // Desconto (ou pacote personalizado abaixo da soma) até aqui: o vendedor envia sozinho.
    'free_discount_percent' => 5,
    // Acima do livre e até aqui: precisa de liberação de admin. Acima: bloqueado.
    'max_discount_percent' => 10,
    // Sugere o nível de cima quando ele custa até X vezes a mensalidade atual.
    'upgrade_threshold' => 1.20,
    // Parcelas permitidas para os valores únicos (implantação, projetos).
    'installments' => [1, 3, 6],
    // Duração usada no "total do contrato".
    'contract_months' => 12,
];
