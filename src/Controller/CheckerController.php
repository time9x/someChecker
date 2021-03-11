<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CheckerController extends AbstractController
{
    private $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * @Route("/checker/{countyID<\d+>}/{masterCategoryId}/{personnelCategoryId<\d+>}/{recipientId<\d+>}/{cnp<\d+>}/", name="checker")
     * @param int $countyID
     * @param int $masterCategoryId
     * @param int $personnelCategoryId
     * @param int $recipientId
     * @param int $cnp
     * @return Response
     */
    public function index(int $countyID, int $masterCategoryId, int $personnelCategoryId, int $recipientId, int $cnp): Response
    {
        $contentJson = [
            'countyID' => $countyID,
            'identificationCode' => $cnp,
            'localityID' => null,
            'masterPersonnelCategoryID' => $masterCategoryId,
            'name' => null,
            'personnelCategoryID' => $personnelCategoryId,
            'recipientID' => $recipientId,
        ];

        $centers = array();
        $campaigns = array();
        $playAlarm = false;
        $result = $this->getHealthCenters($contentJson);
        if ($result) {
            $centers = $result['content'];
            $campaigns = $this->getCampaigns();
            foreach ($campaigns as $key => $campaign) {
                $campaignData = $this->getCampaignData($campaign['campaignID']);
                $campaigns[$key]['vaccineName'] = $campaignData[0]['vaccineName'];
                $campaigns[$key]['daysFromPrev'] = $campaignData[0]['daysFromPrev'];
            }
        }

        foreach ($centers as $center) {
            if ($center['availableSlots'] > 0) {
                $playAlarm = true;
            }
        }

        return $this->render('list.html.twig',
        [
            'centers' => $centers,
            'campaigns' => $campaigns,
            'playAlarm' => $playAlarm,
        ]);
    }

    /**
     * @Route("/", name="list_users")
     */
    public function listUsers()
    {
        $usersJson = [
            'campaignID' => '',
            'cnp' => '',
            'familyName' => '',
            'firstName' => '',
            'fkAccount' => '',
            'servicePersonalUUID' => '',
        ];
        $usersData = $this->getUsers($usersJson);
        $users = array();
        if ($usersData) {
            $users = $usersData['content'];
        }
        return $this->render('base.html.twig',
            [
                'users' => $users,
            ]);
    }

    private function getHealthCenters(array $contentJson): array
    {
        $response = $this->client->request(
            'POST',
            'https://programare.vaccinare-covid.gov.ro/scheduling/api/centres?page=0&size=10&sort=,',
            [
                'headers' => [
                    'cookie' => 'SESSION=' . $_ENV['SITE_COOKIE'],
                ],
                'json' => $contentJson,
            ]
        );
        return $response->toArray();
    }

    private function getCampaigns(): array
    {
        $responseCampaigns = $this->client->request(
            'GET',
            'https://programare.vaccinare-covid.gov.ro/scheduling/api/campaigns/active',
            [
                'headers' => [
                    'cookie' => 'SESSION=' . $_ENV['SITE_COOKIE'],
                ],
            ]
        );
        return $responseCampaigns->toArray();
    }

    private function getCampaignData(string $campaignID): array
    {
        $responseCampaign = $this->client->request(
            'GET',
            'https://programare.vaccinare-covid.gov.ro/scheduling/api/booster/for_campaign/' . $campaignID,
            [
                'headers' => [
                    'cookie' => 'SESSION=' . $_ENV['SITE_COOKIE'],
                ],
            ]
        );
        return $responseCampaign->toArray();
    }

    private function getUsers(array $usersJson): array
    {
        $response = $this->client->request(
            'POST',
            'https://programare.vaccinare-covid.gov.ro/scheduling/api/recipients?page=0&size=10&sort=createdAt,desc',
            [
                'headers' => [
                    'cookie' => 'SESSION=' . $_ENV['SITE_COOKIE'],
                ],
                'json' => $usersJson,
            ]
        );
        return $response->toArray();
    }
}
