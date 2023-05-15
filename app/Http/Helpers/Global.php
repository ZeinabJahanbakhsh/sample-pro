<?php

use App\Models\System\Profile;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\File\File;
use Illuminate\Support\Facades\DB;

/**
 * @param bool $full
 * @return \App\Models\System\Profile|null
 */
function active_profile(bool $full = false): ?Profile
{
    return auth()
        ->user()
        ->profiles()
        ->with($full ? [
            'department',
            'department.departmentType',
            'department.departmentCategory',
            'grade'
        ] : [])
        ->onlyEnabled()
        ->onlyCurrent()
        ->first();
}

/**
 * @return \App\Models\System\Profile|null
 */
function parent_profile(): ?Profile
{
    $active_profile = active_profile();

    if (empty($active_profile)) {
        return null;
    }

    $active_profile->load([
        'parentProfile',
        'department',
        'department.departmentType',
    ]);

    if (empty($active_profile->id)) {
        return null;
    }

    if (!empty($active_profile->parentProfile)) {
        return $active_profile->parentProfile;
    }

    $supervisor_grade_id = $active_profile->department->departmentType->supervisor_grade_id;
    if (empty($supervisor_grade_id)) {
        return null;
    }

    $parent_profile = Profile::where('department_id', $active_profile->department_id)
                             ->where('grade_id', $supervisor_grade_id)
                             ->first();

    if (empty($parent_profile)) {
        return null;
    }

    return $parent_profile;
}

