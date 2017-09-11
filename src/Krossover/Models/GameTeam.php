<?php
namespace Krossover\Models;

/**
 * Class GameTeam
 * @package Krossover\Models
 */
class GameTeam extends Model
{
    const LEGACY_SIDE_TEAM = 'team';
    const LEGACY_SIDE_OPPOSING = 'opposing';

    const TEAM_ORDER_HOME = 1;
    const TEAM_ORDER_AWAY = 2;

    /**
     * @var int
     */
    public $gameId;

    /**
     * @var int
     */
    public $teamId;

    /**
     * @var int
     */
    public $teamOrder;

    /**
     * @var int
     */
    public $finalScore;

    /**
     * @var string
     */
    public $primaryJerseyColor;

    /**
     * @var string
     */
    public $secondaryJerseyColor;

    /**
     * @var string
     */
    public $legacySide;
}
