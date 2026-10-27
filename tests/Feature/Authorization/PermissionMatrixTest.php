<?php

use App\Enums\StudioPermission;
use App\Enums\StudioRole;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * The permission matrix of docs/roles-y-permisos.md, row by row, for the
 * owner, the front desk, the instructor and the student. Self-service rows
 * ("—" for staff) are denied to staff. If a row changes there, it changes
 * here in the same commit.
 *
 * @return array<string, array{bool, bool, bool, bool}>
 */
function expectedPermissionMatrix(): array
{
    return [
        // Business decisions.
        'plans.manage' => [true, false, false, false],
        'schedule.manage' => [true, false, false, false],
        'rules.manage' => [true, false, false, false],
        'reports.view' => [true, false, false, false],
        'studio.manage' => [true, false, false, false],
        // Staff.
        'staff.manage' => [true, false, false, false],
        // Students.
        'students.manage' => [true, true, false, false],
        'students.view_contact' => [true, true, false, false],
        // Payments.
        'plans.sell' => [true, true, false, false],
        'plans.discount' => [true, false, false, false],
        'payments.review' => [true, true, false, false],
        'payments.void_open' => [true, true, false, false],
        'payments.void_closed' => [true, false, false, false],
        'cash.operate' => [true, true, false, false],
        // Sessions.
        'bookings.manage' => [true, true, false, false],
        'bookings.override' => [true, false, false, false],
        'sessions.substitute' => [true, true, false, false],
        'sessions.cancel' => [true, true, false, false],
        'sessions.view' => [true, true, true, false],
        'attendance.mark' => [true, true, true, false],
        // Self-service.
        'self.buy_plan' => [false, false, false, true],
        'self.book' => [false, false, false, true],
        'self.view' => [false, false, false, true],
    ];
}

dataset('permission matrix', function () {
    $roles = [StudioRole::Owner, StudioRole::FrontDesk, StudioRole::Instructor, StudioRole::Student];

    foreach (expectedPermissionMatrix() as $code => $row) {
        foreach ($roles as $column => $role) {
            yield "{$role->label()} · {$code}" => [StudioPermission::from($code), $role, $row[$column]];
        }
    }
});

it('grants each permission exactly as the matrix says', function (StudioPermission $permission, StudioRole $role, bool $allowed) {
    $studio = Studio::factory()->create();
    $user = User::factory()->memberOf($studio, $role)->create();

    expect(Gate::forUser($user)->allows($permission->value, $studio))->toBe($allowed);
})->with('permission matrix');

it('has one row for every permission', function () {
    $codes = array_map(fn (StudioPermission $permission): string => $permission->value, StudioPermission::cases());

    expect(array_keys(expectedPermissionMatrix()))->toEqualCanonicalizing($codes);
});

it('denies every permission in a studio the user does not belong to', function (StudioPermission $permission) {
    $owner = User::factory()->memberOf(Studio::factory()->create(), StudioRole::Owner)->create();

    expect(Gate::forUser($owner)->allows($permission->value, Studio::factory()->create()))->toBeFalse();
})->with(StudioPermission::cases());
