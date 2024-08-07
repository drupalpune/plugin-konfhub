<?php

namespace MauticPlugin\KonfhubBundle\Tests\Functional;

use GuzzleHttp\Handler\MockHandler;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\CoreBundle\Tests\Functional\CreateTestEntitiesTrait;
use Mautic\IntegrationsBundle\Entity\ObjectMapping;
use Mautic\IntegrationsBundle\Entity\ObjectMappingRepository;
use Mautic\IntegrationsBundle\Sync\SyncDataExchange\Internal\Object\Contact;
use Mautic\LeadBundle\Entity\Lead;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LeadGenFromWebhookPayloadTest extends MauticMysqlTestCase
{
    use CreateTestEntitiesTrait;

    const ID = '12ab34cd';
    private ?object $clientMockHandler;

    public function dataForValidation(): iterable
    {
        yield 'empty payload' => [
            '',
            Response::HTTP_BAD_REQUEST
        ];

        yield 'empty json payload' => [
            '{}',
            Response::HTTP_BAD_REQUEST
        ];

        yield 'When Id is missing' => [
            '{"Event Type":"lead","Data":{"Attendee Details":{"Name":"John Doe","Email Address":"john@doe.com","Booking Id":"1234asdc1"}}}',
            Response::HTTP_BAD_REQUEST
        ];
    }

    protected function setUp(): void
    {
        $this->configParams['composer_updates'] = 'testUpdateRunChecksAction' !== $this->getName();

        parent::setUp();

        $this->clientMockHandler = static::getContainer()->get(MockHandler::class);
    }

    /**
     * @dataProvider dataForValidation
     */
    public function testLeadGenFromWebhookPayloadValidation(string $payload, int $expectedStatus): void
    {
        $this->client->request(Request::METHOD_POST, '/plugin/webhook/konfhub', [], [], [], $payload);
        $clientResponse = $this->client->getResponse();
        $this->assertSame($expectedStatus, $clientResponse->getStatusCode());
    }

    public function testLeadGenFromWebhookPayload(): void
    {
        $name    = 'John Doe';
        $emailId = 'john@doe.com';
        $content = $this->getPayload($name, $emailId);

        $leadRepo = $this->em->getRepository(Lead::class);
        $leads = $leadRepo->findBy(['email' => $emailId]);
        $this->assertCount(0, $leads);

        $this->client->request(Request::METHOD_POST, '/plugin/webhook/konfhub', [], [], [], $content);
        $clientResponse = $this->client->getResponse();
        $this->assertSame(Response::HTTP_OK, $clientResponse->getStatusCode());

        $lead = $leadRepo->findOneBy(['email' => $emailId]);
        $this->assertInstanceOf(Lead::class, $lead);

        // check integration object
        $object = $this->em->getRepository(ObjectMapping::class)->findOneBy([
            'integration'           => 'Konfhub',
            'integrationObjectName' => 'booking',
            'integrationObjectId'   => self::ID,
            'internalObjectName'    => Contact::NAME,
        ]);
        $this->assertInstanceOf(ObjectMapping::class, $object);
        $this->assertSame($object->getInternalObjectId(), $lead->getId());
    }

    public function testLeadGenWhenLeadExistsFromWebhookPayload(): void
    {
        $firstName = 'John';
        $lastName  = 'Doe';
        $emailId   = 'john@doe.com';

        $this->createLead($firstName, $lastName, $emailId);
        $this->em->flush();

        $content = $this->getPayload(sprintf('%s %s', $firstName, $lastName), $emailId);
        $this->client->request(Request::METHOD_POST, '/plugin/webhook/konfhub', [], [], [], $content);

        $clientResponse = $this->client->getResponse();
        $this->assertSame(Response::HTTP_OK, $clientResponse->getStatusCode());

        $lead = $this->em->getRepository(Lead::class)->findOneBy(['email' => $emailId]);
        $this->assertInstanceOf(Lead::class, $lead);
    }

    public function testLeadGenWhenLeadAndMappingExistsFromWebhookPayload(): void
    {
        $firstName = 'John';
        $lastName  = 'Doe';
        $emailId   = 'john@doe.com';

        $lead = $this->createLead($firstName, $lastName, $emailId);
        $this->em->flush();
        $this->createObjectMapping($lead->getId());

        $this->em->flush();

        $content = $this->getPayload(sprintf('%s %s', $firstName, $lastName), $emailId);
        $this->client->request(Request::METHOD_POST, '/plugin/webhook/konfhub', [], [], [], $content);

        $clientResponse = $this->client->getResponse();
        $this->assertSame(Response::HTTP_OK, $clientResponse->getStatusCode());
    }

    private function getPayload(string $name, string $email): string|false
    {
        $data = [
            'Id'         => self::ID,
            'Event Type' => 'lead',
            'Data'       => [
                'Attendee Details' => [
                    'Name'          => $name,
                    'Email Address' => $email,
                    'Booking Id'    => self::ID,
                ],
            ],
        ];

        return json_encode($data);
    }

    private function createObjectMapping(int $leadId): ObjectMapping
    {
        $objectMapping = new ObjectMapping();

        $objectMapping
            ->setIntegration('Konfhub')
            ->setIntegrationObjectName('booking')
            ->setIntegrationObjectId(self::ID)
            ->setInternalObjectName(Contact::NAME)
            ->setInternalObjectId($leadId);

        $this->em->persist($objectMapping);

        return $objectMapping;
    }
}
