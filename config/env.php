<?php
const DEFAULT_DB = 'mysql';
const ENV_MODE =  'DEV';

const DB_SERVER = 'localhost';
const DB_USERNAME = 'root';
const DB_PASSWORD = '';
const DB_NAME = 'SALESDB_LIVE';

// const DB_SERVER = 'tuinstra.iad1-mysql-e2-10a.dreamhost.com';
// const DB_USERNAME = 'salespfadmin';
// const DB_PASSWORD = 'un1t3dn30ngr0up';
// const DB_NAME = 'salesdb_live';

const QNE_SERVER = '202.57.44.70\\qnebss';
const QNE_USERNAME = 'sa';
const QNE_PASSWORD = 'QnE123!@#';
const QNE_DEFAULT_DB = 'UTASI_LIVEDB';

// const UNIS_SERVER = '58.97.168.254';
const UNIS_SERVER = '202.57.44.68';
const UNIS_USERNAME = 'oamsun';
const UNIS_PASSWORD = 'Oams@UN';
const UNIS_DB = 'oams-un';

const COMPANY_DATABASES = [
    "EVERCORP_LIVEDB" => "EVER",
    "LIGHTHOUSE_LIVEDB" => "LEC",
    "UTASI_LIVEDB" => "UTASI",
    "BLMCI_LIVEDB" => "BLMCI",
    "INSPIRE_LIVEDB" => "ILCI",
    "SWIN_LIVEDB" => "SWIN",
    "RTI_LIVE" => "RTI",
    "INNOVONE_LIVE" => "IOI",
    "GATEWAY_LIVE" => "GATEWAY",
    "FFO_LIVE" => "UNFI",
    "TAPADS_LIVE" => "TAMC",
];


const HEADER = [
    'alg' => 'HS256',
    'typ' => 'JWT'
];
const SECRET = 'salescrmapi';

const MAIL_USERNAME = 'noreply@unitedneon.com';
const MAIL_FROM = 'noreply@unitedneon.com';
const MAIL_NAME = 'UNMG Sales Platform Admin';
const MAIL_PASSWORD = 'ydcncqqkjjmtuvkb';

date_default_timezone_set('Asia/Manila');