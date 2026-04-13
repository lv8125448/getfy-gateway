<?php

return [
    'required' => 'The :attribute field is required.',
    'email' => 'The :attribute field must be a valid email address.',
    'min' => [
        'string' => 'The :attribute field must be at least :min characters.',
        'array' => 'The :attribute field must have at least :min items.',
    ],
    'max' => [
        'string' => 'The :attribute field must not be greater than :max characters.',
        'array' => 'The :attribute field must not have more than :max items.',
        'file' => 'The :attribute field must not be greater than :max kilobytes.',
        'numeric' => 'The :attribute field must not be greater than :max.',
    ],
    'mimes' => 'The :attribute field must be a file of type: :values.',
    'confirmed' => 'The :attribute field confirmation does not match.',
    'in' => 'The selected :attribute is invalid.',
    'unique' => 'The :attribute has already been taken.',
    'attributes' => [
        'name' => 'name',
        'email' => 'email',
        'password' => 'password',
        'password_confirmation' => 'password confirmation',
        'current_password' => 'current password',
        'cpf' => 'CPF',
        'phone' => 'phone',
        'product_id' => 'product',
        'payment_method' => 'payment method',
        'coupon_code' => 'coupon code',
        'payment_token' => 'card token',
        'rg_front' => 'RG (front)',
        'rg_back' => 'RG (back)',
        'company_document' => 'company document',
    ],
];
