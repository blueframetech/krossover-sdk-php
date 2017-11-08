<?php
namespace Krossover\Models;

/**
 * Class Team
 * @package Krossover\Models
 */
class Team extends Model
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $gender;

    /**
     * @var boolean
     */
    public $isCanonical;

    /**
     * @var boolean
     */
    public $isCustomerTeam;

    /**
     * @var int
     */
    public $leagueId;

    /**
     * @var int
     */
    public $sportId;

    /**
     * @param array $teamArray
     */
    public function fill(array $teamArray)
    {
        $this->id = $teamArray['id'];
        $this->name = $teamArray['name'];
        $this->gender = $teamArray['gender'];
        $this->isCanonical = $teamArray['isCanonical'];
        $this->isCustomerTeam = $teamArray['isCustomerTeam'];
        $this->leagueId = $teamArray['leagueId'];
        $this->sportId = $teamArray['sportId'];
    }

    /**
     * @param $name
     * @param Team $team
     */
    public function fillFromTeam($name, Team $team)
    {
        $this->name = $name;
        $this->gender = $team->gender;
        $this->isCanonical = false;
        $this->isCustomerTeam = false;
        $this->leagueId = $team->leagueId;
        $this->sportId = $team->sportId;
    }
}
