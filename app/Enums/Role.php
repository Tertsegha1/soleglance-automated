<?php

namespace App\Enums;

enum Role: string
{
    case User = 'user';
    case Admin = 'admin';
    // Public-health partner: receives outbreak alerts and reviews health signals.
    case HealthOfficer = 'health_officer';
}
