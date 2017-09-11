<?php
namespace Krossover\Models;

/**
 * Class Game
 * @package Krossover\Models
 */
class Game extends Model
{
    const TYPE_CONFERENCE = 1;
    const TYPE_NON_CONFERENCE = 2;
    const TYPE_PLAYOFF = 3;
    const TYPE_SCOUTING = 4;
    const TYPE_SCRIMMAGE = 5;

    const GENDER_MALE = 'Male';
    const GENDER_FEMALE = 'Female';

    /**
     * @var int
     */
    public $id;

    /**
     * @var \DateTime
     * type="string"
     * description="ISO-8601 format"
     */
    public $datePlayed;

    /**
     * @var int
     */
    public $type;

    /**
     * @var string
     */
    public $gender;

    /**
     * @var int
     */
    public $sportId;

    /**
     * @var int
     */
    public $createdByTeamId;

    /**
     * @var int
     */
    public $createdByUserId;

    /**
     * @var int
     */
    public $teamHomeId;

    /**
     * @var int
     */
    public $teamAwayId;

    /**
     * @throws \Exception
     */
    public function validate()
    {
        $this->validateType();
        $this->validateSport();
        $this->validateGender();

        if (empty($this->teamAwayId) || empty($this->teamHomeId)) {
            throw new \Exception('You must specify the home and away teams.');
        }

        if (empty($this->createdByTeamId) || empty($this->createdByUserId)) {
            throw new \Exception('You must specify the uploader user and team.');
        }
    }

    /**
     * @throws \Exception
     */
    private function validateGender()
    {
        if (!in_array(
            $this->gender,
            [
                self::GENDER_FEMALE,
                self::GENDER_MALE
            ]
        )) {
            throw new \Exception('Check the gender defined for the game. Value not inside defined genders.');
        }
    }

    /**
     * @throws \Exception
     */
    private function validateType()
    {
        if (!in_array(
            $this->type,
            [
                self::TYPE_CONFERENCE,
                self::TYPE_NON_CONFERENCE,
                self::TYPE_PLAYOFF,
                self::TYPE_SCOUTING,
                self::TYPE_SCRIMMAGE
            ]
        )) {
            throw new \Exception('Check the type defined for the game. Value not inside defined types.');
        }
    }

    /**
     * @throws \Exception
     */
    private function validateSport()
    {
        if (!in_array(
            $this->sportId,
            [
                Sport::BASEBALL_SPORT_ID,
                Sport::BASKETBALL_SPORT_ID,
                Sport::FIELD_HOCKEY_SPORT_ID,
                Sport::FOOTBALL_SPORT_ID,
                Sport::ICE_HOCKEY_SPORT_ID,
                Sport::LACROSSE_SPORT_ID,
                Sport::SOCCER_SPORT_ID,
                Sport::TENNIS_SPORT_ID,
                Sport::VOLLEYBALL_SPORT_ID,
                Sport::WRESTLING_SPORT_ID
            ]
        )) {
            throw new \Exception('Check the sport defined for the game. Value not inside known sports.');
        }
    }
}
