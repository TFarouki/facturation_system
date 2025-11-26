<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class UserTrackingObserver
{
    public function creating(Model $model)
    {
        if (Auth::check()) {
            $model->created_by = Auth::id();
            $model->updated_by = Auth::id();
        }
    }

    public function updating(Model $model)
    {
        if (Auth::check()) {
            $model->updated_by = Auth::id();
        }
    }

    public function deleting(Model $model)
    {
        \Illuminate\Support\Facades\Log::info('UserTrackingObserver: deleting event fired for ' . get_class($model) . ' ID: ' . $model->id);
        
        if (Auth::check()) {
            \Illuminate\Support\Facades\Log::info('UserTrackingObserver: User is authenticated. ID: ' . Auth::id());
            
            $usesSoftDeletes = in_array('Illuminate\\Database\\Eloquent\\SoftDeletes', class_uses_recursive($model));
            \Illuminate\Support\Facades\Log::info('UserTrackingObserver: Uses SoftDeletes? ' . ($usesSoftDeletes ? 'Yes' : 'No'));

            if ($usesSoftDeletes) {
                $model->deleted_by = Auth::id();
                $model->saveQuietly(); // Save without triggering events to avoid infinite loop
                \Illuminate\Support\Facades\Log::info('UserTrackingObserver: deleted_by set to ' . Auth::id());
            }
        } else {
            \Illuminate\Support\Facades\Log::warning('UserTrackingObserver: User is NOT authenticated during deletion.');
        }
    }
}
