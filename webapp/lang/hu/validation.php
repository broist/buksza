<?php
return [
    'required' => 'A(z) :attribute mező kitöltése szükséges.',
    'email' => 'Érvényes e-mail-címet adj meg.',
    'confirmed' => 'A két jelszó nem egyezik.',
    'integer' => 'A(z) :attribute mezőben egész számot adj meg.',
    'numeric' => 'A(z) :attribute mezőben számot adj meg.',
    'min' => ['string' => 'Legalább :min karaktert adj meg.', 'numeric' => 'Az érték legalább :min legyen.'],
    'max' => ['string' => 'Legfeljebb :max karaktert adj meg.', 'numeric' => 'Az érték legfeljebb :max lehet.', 'array' => 'Legfeljebb :max elem adható meg.'],
    'between' => ['numeric' => 'Az érték :min és :max között legyen.'],
    'date_format' => 'Érvényes dátumot adj meg.',
    'regex' => 'A(z) :attribute formátuma nem megfelelő.',
    'exists' => 'A kiválasztott érték nem elérhető.',
    'attributes' => ['name'=>'név','email'=>'e-mail-cím','password'=>'jelszó','amount'=>'összeg','date'=>'dátum','savings_rate'=>'megtakarítási arány','opening_balance'=>'nyitóegyenleg','opening_amount'=>'nyitó összeg','subcategory'=>'alkategória','category_id'=>'kategória'],
];
