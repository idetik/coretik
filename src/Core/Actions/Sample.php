<?php

// namespace Coretik\Core\Actions;

// class Sample implements ActionFrontInterface
// {
//     public function getRequired()
//     {
//         return [
//             'token',
//             'answer',
//         ];
//     }

//     public function run($data)
//     {
//         $token = \esc_sql($data['token']);
//         if (!isValidToken($token)) {
//             throw new Exception('Token invalide');
//         }
//         // Do action
//     }

//     public function onError(Exception $error)
//     {
//         // \Globalis\WP\Cubi\include_template_part('templates/standalone/convocation', $_REQUEST + ['form-error' => $error->getMessage()]);
//         exit;
//     }
// }
