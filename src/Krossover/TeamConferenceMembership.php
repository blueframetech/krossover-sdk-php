<?php
namespace Krossover;

class TeamConferenceMembership implements Interfaces\Environment
{
    use Traits\Request;

    /**
     * @var int
     */
    private $teamId;

    /**
     * @var array
     */
    private $teamConferenceMemberships = [];

    /**
     * @var Models\ConferenceMembership;
     */
    public $primaryConference;

    /**
     * FilmExchange constructor.
     * @param $isProductionEnvironment
     * @param $krossoverToken
     * @param $clientId
     * @param $teamId
     */
    public function __construct(
        $isProductionEnvironment,
        $krossoverToken,
        $clientId,
        $teamId
    )
    {
        $this->setKrossoverUri($isProductionEnvironment);
        $this->setHeaders($krossoverToken, $clientId);

        $this->teamId = $teamId;

        $this->getTeamConferenceMemberships();
    }

    /**
     * @throws \Exception
     */
    private function getTeamConferenceMemberships()
    {
        $uri = "/intelligence-api/v2/teams/{$this->teamId}/conference-memberships";

        try {
            $response = $this->jsonRequest(
                'GET',
                $uri
            );
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), 0, $e);
        }

        $this->orderConferences($response);
    }

    /**
     * @param $conferenceMemberships
     */
    private function orderConferences($conferenceMemberships)
    {
        foreach ($conferenceMemberships as $conferenceMembershipItem) {
            $conferenceMembership = new Models\ConferenceMembership();
            $conferenceMembership->fill($conferenceMembershipItem);

            $this->teamConferenceMemberships[$conferenceMembership->key] = $conferenceMembership;

            if ($conferenceMembership->isPrimary) {
                $this->primaryConference = $conferenceMembership;
            }
        }
    }
}
