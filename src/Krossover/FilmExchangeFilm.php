<?php
namespace Krossover;

class FilmExchangeFilm implements Interfaces\Environment
{
    use Traits\Request;

    const FILM_SHARE_URI = '/intelligence-api/v2/conference-film-exchanges/';

    /**
     * @var Models\FilmExchangeFilm
     */
    private $filmExchangeFilm;

    /**
     * FilmExchange constructor.
     * @param $isProductionEnvironment
     * @param $krossoverToken
     * @param $clientId
     */
    public function __construct(
        $isProductionEnvironment,
        $krossoverToken,
        $clientId
    ) {
        $this->setKrossoverUri($isProductionEnvironment);
        $this->setHeaders($krossoverToken, $clientId);

        $this->filmExchangeFilm = new Models\FilmExchangeFilm();
    }

    /**\
     * @param $sportsAssociation
     * @param $conference
     * @param $gender
     * @param $sportId
     * @param $gameId
     * @param $videoId
     * @param $addedByTeamId
     * @param $addedByUserId
     * @throws \Exception
     */
    public function shareToFilmExchange(
        $sportsAssociation,
        $conference,
        $gender,
        $sportId,
        $gameId,
        $videoId,
        $addedByTeamId,
        $addedByUserId
    ) {
        $this->filmExchangeFilm->fill(
            $sportsAssociation,
            $conference,
            $gender,
            $sportId,
            $gameId,
            $videoId,
            $addedByTeamId,
            $addedByUserId
        );

        $uri = self::FILM_SHARE_URI.implode('+', [ $sportsAssociation, $conference, $gender, $sportId ])."/films";

        try {
            $response = $this->jsonRequest('POST', $uri, $this->filmExchangeFilm);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), 0, $e);
        }

        $this->filmExchangeFilm->id = $response['id'];
        $this->filmExchangeFilm->createdAt = $response['createdAt'];
        $this->filmExchangeFilm->updatedAt = $response['updatedAt'];
    }
}
