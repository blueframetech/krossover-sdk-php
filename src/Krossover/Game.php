<?php
namespace Krossover;

use Krossover\Models;

class Game implements Interfaces\Environment
{
    use Traits\Request;

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
        $this->homeTeam->finalScore = $finalScore;
        $this->homeTeam->primaryJerseyColor = $primaryJerseyColor;
        $this->homeTeam->secondaryJerseyColor = $secondaryJerseyColor;
    }

    /**
     * @param $guid
     */
    public function setVideo($guid)
    {
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
            $this->game->validate();
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
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
            throw new \Exception($e->getMessage());
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
            throw new \Exception('No remaining breakdowns for your account. Please contact ');
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
            throw new \Exception($e->getMessage());
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
            throw new \Exception($e->getMessage());
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
            throw new \Exception($e->getMessage());
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
            throw new \Exception($e->getMessage());
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
            throw new \Exception($e->getMessage());
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
            throw new \Exception($e->getMessage());
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
            throw new \Exception($e->getMessage());
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
}
