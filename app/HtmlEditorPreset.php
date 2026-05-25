<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $html
 */
class HtmlEditorPreset extends Model
{
    protected $table = 'html_editor_presets';

    protected $guarded = [];
}
