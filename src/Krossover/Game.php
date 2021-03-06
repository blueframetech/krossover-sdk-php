<?php
namespace Krossover;

use Krossover\Models;

class Game implements Interfaces\Environment
{
    use Traits\Request;
    use Traits\Logging;

    const GAME_TYPE = 'games';
    const GAME_TEAM_TYPE = 'game-teams';
    const GAME_VIDEO_TYPE = 'game-videos';

    const GAME_URI = '/intelligence-api/v3/games';

    /**
     * @var Models\Game
     */
    private $game;

    /**
     * @var Models\GameTeam
     */
    private $homeTeam;

    /**
     * @var Models\GameTeam
     */
    private $awayTeam;

    /**
     * @var Models\GameVideo
     */
    private $gameVideo;

    /**
     * @var array
     */
    private $homeTeamRoster;

    /**
     * @var array
     */
    private $awayTeamRoster;

    /**
     * @var boolean
     */
    private $submittedForBreakdown = false;

    /**
     * @var int
     */
    private $videoId;
    /**
     * @var
     */
    private $isProductionEnvironment;
    /**
     * @var
     */
    private $krossoverToken;
    /**
     * @var
     */
    private $clientId;

    /**
     * Game constructor.
     * @param \DateTime $datePlayed
     * @param $type
     * @param $gender
     * @param $createdByTeamId
     * @param $createdByUserId
     * @param $sportId
     * @param $isProductionEnvironment
     * @param $krossoverToken
     * @param $clientId
     */
    public function __construct(
        \DateTime $datePlayed,
        $type,
        $gender,
        $createdByTeamId,
        $createdByUserId,
        $sportId,
        $isProductionEnvironment,
        $krossoverToken,
        $clientId
    ) {
        $this->game = new Models\Game();

        $this->game->datePlayed = $datePlayed;
        $this->game->type = $type;
        $this->game->gender = $gender;
        $this->game->createdByTeamId = $createdByTeamId;
        $this->game->createdByUserId = $createdByUserId;
        $this->game->sportId = $sportId;

        $this->typesDictionary = [
            'createdByTeam' => 'teams',
            'createdByUser' => 'users',
            'teamHome' => 'teams',
            'teamAway' => 'teams'
        ];

        $this->setKrossoverUri($isProductionEnvironment);
        $this->setHeaders($krossoverToken, $clientId);
        $this->isProductionEnvironment = $isProductionEnvironment;
        $this->krossoverToken = $krossoverToken;
        $this->clientId = $clientId;
    }

    /**
     * @param $teamId
     * @param $finalScore
     * @param $primaryJerseyColor
     * @param null $secondaryJerseyColor
     */
    public function setHomeTeam($teamId, $finalScore, $primaryJerseyColor, $secondaryJerseyColor = null)
    {
        $this->game->teamHomeId = $teamId;
        $this->homeTeam = new Models\GameTeam();
        $this->homeTeam->teamId = $teamId;
        $this->homeTeam->legacySide = Models\GameTeam::LEGACY_SIDE_TEAM;
        $this->homeTeam->teamOrder = Models\GameTeam::TEAM_ORDER_HOME;
        $this->homeTeam->finalScore = $finalScore;
        $this->homeTeam->primaryJerseyColor = $primaryJerseyColor;
        $this->homeTeam->secondaryJerseyColor = $secondaryJerseyColor;
    }

    /**
     * @param $teamId
     * @param $finalScore
     * @param $primaryJerseyColor
     * @param null $secondaryJerseyColor
     */
    public function setAwayTeam($teamId, $finalScore, $primaryJerseyColor, $secondaryJerseyColor = null)
    {
        $this->game->teamAwayId = $teamId;
        $this->awayTeam = new Models\GameTeam();
        $this->awayTeam->teamId = $teamId;
        $this->awayTeam->legacySide = Models\GameTeam::LEGACY_SIDE_OPPOSING;
        $this->awayTeam->teamOrder = Models\GameTeam::TEAM_ORDER_AWAY;
        $this->awayTeam->finalScore = $finalScore;
        $this->awayTeam->primaryJerseyColor = $primaryJerseyColor;
        $this->awayTeam->secondaryJerseyColor = $secondaryJerseyColor;
    }

    /**
     * Creates an non-canonical, non-customer team as the opponent team
     *
     * @param $teamName
     * @param $finalScore
     * @param $primaryJerseyColor
     * @param null $secondaryJerseyColor
     * @throws \Exception
     */
    public function createOpponentTeam($teamName, $finalScore, $primaryJerseyColor, $secondaryJerseyColor = null)
    {
        if (empty($this->homeTeam) && empty($this->awayTeam)) {
            throw new \Exception('You must define the opponent team before creating a new team');
        }

        $baseTeam = (!empty($this->homeTeam)) ? $this->homeTeam : $this->awayTeam;

        $teamRepository = new Team(
            $this->isProductionEnvironment,
            $this->krossoverToken,
            $this->clientId
        );


        $team = $teamRepository->createNonCustomerTeamFromTeam($teamName, $baseTeam->teamId);

        if (!empty($this->homeTeam)) {
            $this->setAwayTeam($team->id, $finalScore, $primaryJerseyColor, $secondaryJerseyColor);
        } else {
            $this->setHomeTeam($team->id, $finalScore, $primaryJerseyColor, $secondaryJerseyColor);
        }
    }

    /**
     * @param $guid
     */
    public function setVideo($guid)
    {
        try {
            $this->log("{$guid} attached to a game.");
        } catch (\Exception $e) {

        }

        $this->gameVideo = new Models\GameVideo();
        $this->gameVideo->guid = $guid;

        if (!empty($this->game->id)) {
            $this->sendGameVideoRequest();
        }
    }

    /**
     * @throws \Exception
     */
    public function saveGame()
    {
        try {
            $this->log("{$this->gameVideo->guid} starting to save game between {$this->homeTeam->teamId} - {$this->awayTeam->teamId}.");
        } catch (\Exception $e) {
        }

        try {
            $this->game->validate();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), 0, $e);
        }

        $this->sendSaveGameRequest();
        $this->sendSetTeamsRequest();
        $this->homeTeamRoster = $this->getRostersForTeam($this->homeTeam->teamId, $this->homeTeam->teamOrder);
        $this->awayTeamRoster = $this->getRostersForTeam($this->awayTeam->teamId, $this->awayTeam->teamOrder);
    }

    /**
     * @param bool $softFailWhenNoRemainingBreakdowns
     * @throws \Exception
     */
    public function saveGameAndSubmitForBreakdown($softFailWhenNoRemainingBreakdowns = false)
    {
        $setVideo = empty($this->game->id) ? true : false;

        $this->saveGame();
        if ($setVideo) {
            $this->sendGameVideoRequest();
        }

        $this->submitForBreakdown($softFailWhenNoRemainingBreakdowns);
    }

    /**
     * @throws \Exception
     */
    public function validateGame()
    {
        try {
            $this->game->validate();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), 0, $e);
        }
        if (empty($this->gameVideo)) {
            throw new \Exception('Video must be uploaded and set before submitting the game for breakdown');
        }
    }

    /**
     * @param bool $softFailWhenNoRemainingBreakdowns
     * @throws \Exception
     */
    public function submitForBreakdown($softFailWhenNoRemainingBreakdowns = false)
    {
        $this->validateGame();
        $remainingBreakdowns = $this->getRemainingBreakdowns();

        if (($remainingBreakdowns === 0) && (!$softFailWhenNoRemainingBreakdowns)) {
            throw new \Exception('No remaining breakdowns for your account. Please contact support@krossover.com.');
        }

        //We get the saved game to get the video id
        $game = $this->getGameFromKOApi($this->game->id);

        if (($remainingBreakdowns > 0) && ($this->sendSubmitForBreakdownRequest($game))) {
            $this->submittedForBreakdown = true;
        }
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    private function getRemainingBreakdowns()
    {
        $uri = implode(
            "/",
            [
                '/intelligence-api/v1/teams',
                $this->game->createdByTeamId,
                'remainingBreakdowns'
            ]
        );

        try {
            $response = $this->jsonRequest(
                'GET',
                $uri
            );
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), 0, $e);
        }

        if ((!key_exists('packageGamesRemaining', $response)) || (!key_exists('planGamesRemaining', $response))) {
            return 0;
        }

        return $response['packageGamesRemaining']+$response['planGamesRemaining'];
    }

    /**
     * @param $gameId
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    public function getGameFromKOApi($gameId)
    {
        $uri = implode(
            "/",
            [
                self::GAME_URI,
                $gameId
            ]
        );

        $parameters = 'include=gameTeams,gameTeams.team,gameTeams.team.school,gameTeams.team.school.address,videos,videos.breakdowns';

        try {
            $response = $this->koJsonApiRequest(
                'GET',
                implode ("?", [ $uri, $parameters ])
            );
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), 0, $e);
        }

        return $response;
    }

    /**
     * @return Models\Game
     */
    public function getGame()
    {
        return $this->game;
    }

    /**
     * @return Models\Game
     */
    public function getVideoId()
    {
        return $this->videoId;
    }

    /**
     * @return bool
     */
    public function isSubmittedForBreakdown()
    {
        return $this->submittedForBreakdown;
    }

    /**
     * @throws \Exception
     */
    private function sendSaveGameRequest()
    {
        $data = [];
        $data['data'] = $this->transformBodyToJsonApiSpec(
            self::GAME_TYPE,
            $this->game
        );

        try {
            $response = $this->koJsonApiRequest('POST', self::GAME_URI, $data);
        } catch (\Exception $e) {
            $game = serialize($this);
            $this->log("{$e->getMessage()}");
            $this->log("{$game}");
            throw new \Exception($e->getMessage(), 0, $e);
        }

        $this->game->id = $response['id'];
        $this->homeTeam->gameId = $this->game->id;
        $this->awayTeam->gameId = $this->game->id;
    }

    /**
     * @throws \Exception
     */
    private function sendSetTeamsRequest()
    {
        $uri = implode("/", [ self::GAME_URI, $this->game->id, 'set-game-teams' ]);

        $data = [];
        $data['data'] = [];
        $data['data'][] = $this->transformBodyToJsonApiSpec(
            self::GAME_TEAM_TYPE,
            $this->homeTeam
        );
        $data['data'][] = $this->transformBodyToJsonApiSpec(
            self::GAME_TEAM_TYPE,
            $this->awayTeam
        );

        try {
            $this->koJsonApiRequest('POST', $uri, $data);
        } catch (\Exception $e) {
            $game = serialize($this);
            $this->log("{$e->getMessage()}");
            $this->log("{$game}");
            throw new \Exception($e->getMessage(), 0, $e);
        }
    }

    /**
     * @param $teamId
     * @param $teamOrder
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    private function getRostersForTeam($teamId, $teamOrder)
    {
        $uri = implode(
            "/",
            [
                self::GAME_URI,
                $this->game->id,
                'rosters',
                implode("+", [ $teamId, $teamOrder ])
            ]
        );

        $parameters = 'include=teamRoster,teamRoster.teamSeason';

        try {
            $response = $this->koJsonApiRequest(
                'GET',
                implode ("?", [ $uri, $parameters ])
            );
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), 0, $e);
        }

        return $response;
    }

    /**
     * @throws \Exception
     */
    private function sendGameVideoRequest()
    {
        $uri = implode("/", [ self::GAME_URI, $this->game->id, 'add-game-video' ]);

        $data = [];
        $data['data'] = $this->transformBodyToJsonApiSpec(
            self::GAME_VIDEO_TYPE,
            $this->gameVideo
        );

        try {
            $this->koJsonApiRequest('POST', $uri, $data);
        } catch (\Exception $e) {
            $game = serialize($this);
            $this->log("{$e->getMessage()}");
            $this->log("{$game}");
            throw new \Exception($e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws \Exception
     */
    private function sendSubmitForBreakdownRequest($game)
    {
        $videoId = $this->getVideoIdFromGameResponse($game);
        $this->videoId = $videoId;

        $uri = implode("/", [ self::GAME_URI, $this->game->id, 'submit-for-breakdown' ]);

        $body = [
            'videoId' => $videoId,
            'gameId' => $this->game->id,
            'submittedByTeamId' => $this->game->createdByTeamId,
            'submittedByUserId' => $this->game->createdByUserId,
            'type' => 'regular'
        ];

        try {
            $this->jsonRequest('POST', $uri, $body);
        } catch (\Exception $e) {
            $game = serialize($this);
            $this->log("{$e->getMessage()}");
            $this->log("{$game}");
            throw new \Exception($e->getMessage(), 0, $e);
        }
    }

    /**
     * @param $game
     * @return mixed
     * @throws \Exception
     */
    private function getVideoIdFromGameResponse($game)
    {
        if (!empty($game['videosId'])) {
            if (count($game['videosId']) === 1) {
                return $game['videosId'][0];
            }
        }

        throw new \Exception('This game cannot be submited for breakdown.');
    }

    /**
     *
     */
    public function shareWithTeamsPrimaryConferences()
    {
        $filmExchanges = $this->getPrimaryConferences();

        foreach ($filmExchanges as $filmExchange) {
            $this->shareToFilmExchange(
                $filmExchange->sportsAssociation,
                $filmExchange->conference,
                $filmExchange->gender,
                $filmExchange->sportId
            );
        }
    }

    /**
     * @return array
     */
    public function getPrimaryConferences()
    {
        $conferences = [];
        
        if (isset($this->game->teamHomeId)) {
            $conferenceMemberships = new TeamConferenceMembership(
                $this->isProductionEnvironment,
                $this->krossoverToken,
                $this->clientId,
                $this->game->teamHomeId
            );
            if (!empty($conferenceMemberships->primaryConference) && ($conferenceMemberships->primaryConference->isFilmExchange)) {
                $conferences[$conferenceMemberships->primaryConference->key] = $conferenceMemberships->primaryConference;
            }
        }

        if (isset($this->game->teamAwayId)) {
            $conferenceMemberships = new TeamConferenceMembership(
                $this->isProductionEnvironment,
                $this->krossoverToken,
                $this->clientId,
                $this->game->teamAwayId
            );
            if (!empty($conferenceMemberships->primaryConference) && ($conferenceMemberships->primaryConference->isFilmExchange)) {
                $conferences[$conferenceMemberships->primaryConference->key] = $conferenceMemberships->primaryConference;
            }
        }
        return $conferences;
    }

    /**
     * @param $sportsAssociation
     * @param $conference
     * @param $gender
     * @param $sportId
     * @throws \Exception
     */
    public function shareToFilmExchange(
        $sportsAssociation,
        $conference,
        $gender,
        $sportId
    ) {
        if (!empty($this->game->id) && (!empty($this->videoId))) {
            $filmExchangeFilm = new FilmExchangeFilm($this->isProductionEnvironment, $this->krossoverToken, $this->clientId);
            $filmExchangeFilm->shareToFilmExchange(
                $sportsAssociation,
                $conference,
                $gender,
                $sportId,
                $this->game->id,
                $this->videoId,
                $this->game->createdByTeamId,
                $this->game->createdByUserId
            );
        } else {
            throw new \Exception('The game must be saved first.');
        }
    }
}
