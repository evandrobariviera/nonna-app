<?php

// Traduções usadas por App\Http\Requests\Auth\LoginRequest (login da equipe,
// guard "web") e App\Http\Requests\Portal\LoginRequest (login do cliente,
// guard "portal") — sem esse arquivo, trans('auth.failed')/trans('auth.throttle')
// caem no fallback do Laravel e mostram a chave crua ("auth.failed") na tela
// em vez de uma mensagem legível.

return [

    'failed'   => 'E-mail ou senha incorretos.',
    'password' => 'A senha informada está incorreta.',
    'throttle' => 'Muitas tentativas de login. Tente novamente em :seconds segundos.',

];
