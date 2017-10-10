<?php
namespace Krossover\Models;

/**
 * Class ConferenceMembership
 * @package Krossover\Models
 */
class ConferenceMembership
{
    /**
     * @var string
     */
    public $key;

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
     * @var
     */
    public $isPrimary;

    /**
     * @var
     */
    public $isFilmExchange;

    /**
     * @var
     */
    public $name;

    /**
     * @var
     */
    public $isVisibleToTeams;

    /**
     * @var
     */
    public $readOnly;

    /**
     * @param $conferenceMembership
     */
    public function fill(array $conferenceMembership)
    {
        $this->sportsAssociation = $conferenceMembership['sportsAssociation'];
        $this->conference =  (!empty($conferenceMembership['conference'])) ? $conferenceMembership['conference']['code'] : null;
        $this->gender = $conferenceMembership['gender'];
        $this->sportId = $conferenceMembership['sportId'];
        $this->isPrimary = (!is_null($conferenceMembership['isPrimary'])) ? true : false;
        $this->isFilmExchange = (!empty($conferenceMembership['filmExchange'])) ? true : false;
        $this->name = (!empty($conferenceMembership['filmExchange'])) ? $conferenceMembership['filmExchange']['name'] : null;
        $this->isVisibleToTeams = (!empty($conferenceMembership['filmExchange'])) ? $conferenceMembership['filmExchange']['isVisibleToTeams'] : null;
        $this->readOnly = $conferenceMembership['filmExchangeReadOnly'];
        $this->key = implode('+',
            [
                $this->sportsAssociation,
                $this->conference,
                $this->gender,
                $this->sportId
            ]
        );
    }
}
