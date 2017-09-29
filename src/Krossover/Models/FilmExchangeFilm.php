<?php
namespace Krossover\Models;

/**
 * Class FilmExchangeFilm
 * @package Krossover\Models
 */
class FilmExchangeFilm
{
    /**
     * @var string
     */
    public $sportsAssociation;

    /**
     * @var string
     */
    public $conference;

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
    public $gameId;

    /**
     * @var int
     */
    public $videoId;

    /**
     * @var int
     */
    public $addedByTeamId;

    /**
     * @var int
     */
    public $addedByUserId;

    /**
     * @var int
     */
    public $id;

    /**
     * @var \DateTime
     * type="string"
     * description="ISO-8601 format"
     */
    public $createdAt;

    /**
     * @var \DateTime
     * type="string"
     * description="ISO-8601 format"
     */
    public $updatedAt;

    /**
     * @param $sportsAssociation
     * @param $conference
     * @param $gender
     * @param $sportId
     * @param $gameId
     * @param $videoId
     * @param $addedByTeamId
     * @param $addedByUserId
     */
    public function fill(
        $sportsAssociation,
        $conference,
        $gender,
        $sportId,
        $gameId,
        $videoId,
        $addedByTeamId,
        $addedByUserId
    ) {
        $this->sportsAssociation = $sportsAssociation;
        $this->conference = $conference;
        $this->gender = $gender;
        $this->sportId = $sportId;
        $this->gameId = $gameId;
        $this->videoId = $videoId;
        $this->addedByTeamId = $addedByTeamId;
        $this->addedByUserId = $addedByUserId;
    }
}
