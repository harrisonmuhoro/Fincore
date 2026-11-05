<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $table = 'iam_permissions';

    protected $guarded = ['id'];
}
