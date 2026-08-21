<?php

namespace App;

use App\Concerns\UsesHeavyDatabaseConnection;
use Illuminate\Database\Eloquent\Model;

class SearchIndex extends Model
{
    use UsesHeavyDatabaseConnection;
}
