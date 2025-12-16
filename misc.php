<?php

$client_map = [
    "name" => [
        "table" => "clients",
        "column" => "name",
        "value_type" => "string", # string || ID || IDs = ID[]
        "parent_id" => "ID"
    ],
    "industry" => [
        "table" => "clients",
        "column" => "industry",
        "column_table" => "client_misc",
        "value_type" => "ID",
        "parent_id" => "ID"
    ],
    "brand" => [
        "table" => "clients",
        "column" => "brand",
        "value_type" => "string",
        "parent_id" => "ID"
    ],
    "status" => [
        "table" => "clients",
        "column" => "status",
        "column_table" => "client_misc",
        "value_type" => "ID",
        "parent_id" => "ID"
    ],
    "company" => [
        "table" => "clients",
        "label" => "company",
        "column" => "company_id",
        "column_table" => "companies",
        "value_type" => "ID",
        "parent_id" => "ID"
    ],
    "sales_unit" => [
        "table" => "clients",
        "label" => "sales_unit",
        "column" => "sales_unit_id",
        "column_table" => "sales_units",
        "value_type" => "ID",
        "parent_id" => "ID"
    ],
    "account_executive" => [
        "table" => "client_accounts",
        "label" => "account_executive",
        "column" => "account_id",
        "column_table" => "user_information",
        "value_type" => "IDs",
        "parent_id" => "client_id"
    ],
    "mediums" => [
        "table" => "client_mediums",
        "label" => "mediums",
        "column" => "medium_id",
        "column_table" => "mediums",
        "column_label" => "medium",
        "value_type" => "IDs",
        "parent_id" => "client_id"
    ],
    "contact_person" => [
        "table" => "client_contact",
        "label" => "contact_person",
        "column" => "name",
        "value_type" => "string",
        "parent_id" => "client_id"
    ],
    "designation" => [
        "table" => "client_contact",
        "column" => "designation",
        "value_type" => "string",
        "parent_id" => "client_id"
    ],
    "contact_number" => [
        "table" => "client_contact",
        "column" => "contact_number",
        "value_type" => "string",
        "parent_id" => "client_id"
    ],
    "email_address" => [
        "table" => "client_contact",
        "column" => "email_address",
        "value_type" => "string",
        "parent_id" => "client_id"
    ],
    "address" => [
        "table" => "client_contact",
        "column" => "address",
        "value_type" => "string",
        "parent_id" => "client_id"
    ],
    "type" => [
        "table" => "client_contact",
        "column" => "type",
        "column_table" => "client_misc",
        "value_type" => "ID",
        "parent_id" => "client_id"
    ],
    "source" => [
        "table" => "client_contact",
        "column" => "source",
        "column_table" => "client_misc",
        "value_type" => "ID",
        "parent_id" => "client_id"
    ],

];
