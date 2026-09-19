<?php
declare(strict_types=1);

/**
 * Canonical Preparation visibility/action boundary.
 *
 * Visibility:
 * - preparation only: assigned areas
 * - shift_supervision: all areas, read-only unless preparation is also granted
 * - admin: all areas, read-only for Preparation operational mutations
 *
 * Actionability:
 * - requires non-admin + preparation capability + assigned area
 * - shift_supervision never expands actionable areas
 */
function preparation_access_context(?array $user=null): array
{
    $user ??= current_user();
    $userId=(int)($user['id']??0);
    if($userId<1){
        return [
            'can_view'=>false,'monitor_only'=>false,'can_mutate'=>false,
            'assigned_areas'=>[],'visible_areas'=>[],'actionable_areas'=>[],
        ];
    }

    $role=(string)($user['role']??'');
    $isAdmin=$role==='admin';
    $hasPreparation=user_has_capability('preparation',$user);
    $hasSupervision=user_has_capability('shift_supervision',$user);
    $assigned=$hasPreparation?user_preparation_areas($userId):[];

    $globalMonitor=$isAdmin||$hasSupervision;
    $visible=$globalMonitor?array_keys(preparation_operational_areas()):$assigned;

    // Admin role is intentionally not an operational Preparation grant.
    // A supervisor+preparation operator may mutate only explicitly assigned areas.
    $actionable=(!$isAdmin&&$hasPreparation)?$assigned:[];

    return [
        'can_view'=>$visible!==[],
        'monitor_only'=>$visible!==[]&&$actionable===[],
        'can_mutate'=>$actionable!==[],
        'assigned_areas'=>$assigned,
        'visible_areas'=>$visible,
        'actionable_areas'=>$actionable,
        'has_preparation'=>$hasPreparation,
        'has_shift_supervision'=>$hasSupervision,
        'is_admin'=>$isAdmin,
    ];
}

function preparation_visible_areas(?array $user=null): array
{
    return preparation_access_context($user)['visible_areas'];
}

function preparation_actionable_areas(?array $user=null): array
{
    return preparation_access_context($user)['actionable_areas'];
}

function preparation_can_mutate_area(string $area,?array $user=null): bool
{
    $area=normalize_preparation_area($area);
    return in_array($area,preparation_actionable_areas($user),true);
}
