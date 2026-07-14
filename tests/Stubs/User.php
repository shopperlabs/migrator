<?php

declare(strict_types=1);

namespace Shopper\Migrator\Tests\Stubs;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}
