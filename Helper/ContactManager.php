<?php

declare(strict_types=1);

namespace MauticPlugin\KonfhubBundle\Helper;

use Mautic\IntegrationsBundle\Entity\ObjectMapping;
use Mautic\IntegrationsBundle\Entity\ObjectMappingRepository;
use Mautic\IntegrationsBundle\Sync\Helper\MappingHelper;
use Mautic\IntegrationsBundle\Sync\SyncDataExchange\Internal\Object\Contact;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Exception\ImportFailedException;
use Mautic\LeadBundle\Model\LeadModel;
use Psr\Log\LoggerInterface;

class ContactManager
{
    public function __construct(
        private LeadModel $leadModel,
        private ObjectMappingRepository $objectMappingRepository,
        private MappingHelper $mappingHelper,
        private LoggerInterface $logger
    ) {
    }

    public function processAndGenerateContact(array $data): void
    {
        $leadRepo    = $this->leadModel->getRepository();
        $contactData = $data['Data']['Attendee Details'];

        $exists = $this->objectMappingRepository->findOneBy([
            'integration'           => 'Konfhub',
            'integrationObjectName' => 'booking',
            'integrationObjectId'   => $contactData['Booking Id'],
            'internalObjectName'    => Contact::NAME,
        ]);

        if (!$exists instanceof ObjectMapping) {
            $contacts = $leadRepo->getContactsByEmail($contactData['Email Address']);
            if (is_array($contacts) && count($contacts) > 0) {
                $lead    = array_pop($contacts);
            } else {
                // Create lead.
                $lead = $this->createLead($contactData);
                $this->logger->info(sprintf('Created new contact for %s', $contactData['Email Address']));
            }

            $this->createObjectMapping($contactData['Booking Id'], $lead->getId(), $data['Id']);

            $this->logger->info(sprintf('Created new mapping for Contact %s', $lead->getName()));
        } else {
            // Update.
            $this->updateContact($exists->getInternalObjectId(), $contactData);
        }
    }

    private function createLead(mixed $contactData): Lead
    {
        $leadData = [
            'firstname' => $contactData['Name'],
            'email' => $contactData['Email Address'],
        ];

        $lead = $this->leadModel->getEntity();

        $this->leadModel->setFieldValues($lead, $leadData);
        $this->leadModel->saveEntity($lead);

        return $lead;
    }

    private function updateContact(int $contactId, array $contactData): void
    {
        $lead = $this->leadModel->getEntity($contactId);
        $leadData = [
            'firstname' => $contactData['Name'],
        ];

        // Update the lead entity with the Drift data.
        $this->leadModel->setFieldValues($lead, $leadData);
        $this->leadModel->saveEntity($lead);

        $this->logger->info(sprintf('Updated contact with %s id.', $lead->getId()));
    }

    private function createObjectMapping(string $bookingId, int $leadId, string $id): void
    {
        $objectMapping = new ObjectMapping();
        $objectMapping
            ->setIntegration('Konfhub')
            ->setIntegrationObjectName('booking')
            ->setIntegrationObjectId($bookingId)
            ->setInternalObjectName(Contact::NAME)
            ->setInternalObjectId($leadId)
            ->setIntegrationReferenceId($id);

        $this->mappingHelper->saveObjectMappings([$objectMapping]);
    }
}
