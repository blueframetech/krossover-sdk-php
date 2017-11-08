<?php
namespace Krossover;

class Team implements Interfaces\Environment
{
    use Traits\Request;

    const TEAM_URI = '/intelligence-api/v3/teams';
    const TEAM_TYPE = 'teams';

    public function __construct(
        $isProductionEnvironment,
        $krossoverToken,
        $clientId
    ) {
        $this->setKrossoverUri($isProductionEnvironment);
        $this->setHeaders($krossoverToken, $clientId);
    }

    /**
     * @param $name
     * @param $teamId
     * @return Models\Team
     */
    public function createNonCustomerTeamFromTeam($name, $teamId)
    {
        $team = $this->getTeam($teamId);

        $newTeam = $this->getOpponentTeamModel($name, $team);

        $this->saveTeam($newTeam);

        return $newTeam;
    }

    /**
     * @param $teamId
     * @return Models\Team
     * @throws \Exception
     */
    public function getTeam($teamId)
    {
        $uri = implode(
            "/",
            [
                self::TEAM_URI,
                $teamId
            ]
        );

        try {
            $response = $this->koJsonApiRequest(
                'GET',
                $uri
            );
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), 0, $e);
        }

        $team = new Models\Team();
        $team->fill($response);

        return $team;
    }

    /**
     * @param $name
     * @param Models\Team $team
     * @return Models\Team
     */
    private function getOpponentTeamModel($name, Models\Team $team)
    {
        $newTeam = new Models\Team();
        $newTeam->fillFromTeam($name, $team);

        return $newTeam;
    }

    /**
     * @param Models\Team $team
     */
    private function saveTeam(Models\Team &$team)
    {
        $data = [];
        $data['data'] = $this->transformBodyToJsonApiSpec(
            self::TEAM_TYPE,
            $team
        );

        try {
            $response = $this->koJsonApiRequest('POST', self::TEAM_URI, $data);
        } catch (\Exception $e) {
            $game = serialize($this);
            $this->log("{$e->getMessage()}");
            $this->log("{$game}");
        }

        $team->id = $response['id'];
    }
}
