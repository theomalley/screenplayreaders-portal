<?php

// v1.0 — 2026-06-21 | Initial: maps GF Form 9 payload keys to budget engine variable names
// The woo_budgeting.php webhook uses sanitize_key(strtolower($field->label)) as keys.
// Hidden computed fields already match (e.g. field 483 label "budget" → "budget").
// User-facing fields with long labels need remapping here.

namespace App\Services\Budget;

class GravityFormFieldMapper
{
    // GF sanitized label → engine variable name (only entries that differ)
    private const LABEL_MAP = [
        // User-facing fields whose labels don't match engine names
        'your_project_title' => 'projecttitle',
        'preparers_first_name' => 'headernamefirst',
        'preparers_last_name' => 'headernamelast',
        'director_name_optional' => 'headerdirector',
        'budget_prepared_date' => 'headerdate',
        'what_kind_of_budget_would_you_like_to_create' => 'budgettype',
        'how_would_you_like_to_budget_your_series' => 'seriestype',
        'state_you_plan_to_shoot_in' => 'shootingstate',
        'what_total_amount_would_you_like_your_budget_to_be' => 'budgetpretty',
        'how_many_cast_members_would_you_like_to_budget_for' => 'usercastsize',
        'would_you_like_to_customize_your_budget' => 'customizebudget',
        'would_you_like_to_use_our_default_number_of_weeks_for_pre-production_shooting_wrap_and_post-production' => 'userusetimedefaults',
        'enter_the_episode_number_of_this_episode' => 'headerepisodenumber',
        'enter_the_episode_title' => 'headerepisodetitle',
        'how_many_episodes_would_you_like_to_use_the_above_budget_for' => 'headernumofepisodes',
        'what_email_address_would_you_like_your_budget_files_emailed_to' => 'email',
        'budget_format' => 'budgetformat',

        // Cast member fields (GF uses "Cast Member N" labels)
        'cast_member_1' => 'cast01', 'cast_member_2' => 'cast02', 'cast_member_3' => 'cast03',
        'cast_member_4' => 'cast04', 'cast_member_5' => 'cast05', 'cast_member_6' => 'cast06',
        'cast_member_7' => 'cast07', 'cast_member_8' => 'cast08', 'cast_member_9' => 'cast09',
        'cast_member_10' => 'cast10', 'cast_member_11' => 'cast11', 'cast_member_12' => 'cast12',
        'cast_member_13' => 'cast13', 'cast_member_14' => 'cast14', 'cast_member_15' => 'cast15',
        'cast_member_16' => 'cast16', 'cast_member_17' => 'cast17', 'cast_member_18' => 'cast18',
        'cast_member_19' => 'cast19', 'cast_member_20' => 'cast20', 'cast_member_21' => 'cast21',
        'cast_member_22' => 'cast22', 'cast_member_23' => 'cast23', 'cast_member_24' => 'cast24',
        'cast_member_25' => 'cast25',

        // Header label computed fields (GF labels are uppercase, engine expects lowercase)
        'headerlabelbudget' => 'headerlabelbudget',
        'headerlabelepisodenumber' => 'headerlabelepisodenumber',
        'headerlabelepisodebudget' => 'headerlabelepisodebudget',
        'headerlabelpipe2' => 'headerlabelpipe2',
        'headerlabeloverallseries' => 'headerlabeloverallseries',
        'headerlabelepisodes' => 'headerlabelepisodes',
        'headerlabelmakeplural' => 'headerlabelmakeplural',
        'headerdollarsign' => 'headerdollarsign',
        'headerlabelperepisode' => 'headerlabelperepisode',
        'headerlabelpipe1' => 'headerlabelpipe1',
    ];

    /**
     * Map a raw GF webhook payload to the variable names the budget engine expects.
     */
    public function map(array $rawPayload): array
    {
        $mapped = [];

        foreach ($rawPayload as $key => $value) {
            $engineKey = self::LABEL_MAP[$key] ?? $key;
            $mapped[$engineKey] = $value;
        }

        return $mapped;
    }
}
