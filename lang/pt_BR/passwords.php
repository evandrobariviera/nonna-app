<?php

// Usado por App\Http\Controllers\Auth\PasswordResetLinkController e
// NewPasswordController via __($status) — o broker de reset de senha do
// Laravel devolve uma dessas chaves como status.

return [

    'reset'     => 'Sua senha foi redefinida!',
    'sent'      => 'Enviamos por e-mail o link de redefinição de senha!',
    'throttled' => 'Aguarde antes de tentar novamente.',
    'token'     => 'Esse token de redefinição de senha é inválido.',
    'user'      => 'Não encontramos nenhum usuário com esse e-mail.',

];
