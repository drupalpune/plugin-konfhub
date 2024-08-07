<?php

declare(strict_types=1);

namespace MauticPlugin\KonfhubBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Controller\CommonController;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Service\FlashBag;
use Mautic\CoreBundle\Translation\Translator;
use MauticPlugin\KonfhubBundle\Helper\ContactManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends CommonController
{
    public function __construct(
        ManagerRegistry $doctrine,
        MauticFactory $factory,
        ModelFactory $modelFactory,
        UserHelper $userHelper,
        CoreParametersHelper $coreParametersHelper,
        EventDispatcherInterface $dispatcher,
        Translator $translator,
        FlashBag $flashBag,
        ?RequestStack $requestStack,
        ?CorePermissions $security,
        private ContactManager $contactManager,
    ) {
        parent::__construct($doctrine, $factory, $modelFactory, $userHelper, $coreParametersHelper, $dispatcher, $translator, $flashBag, $requestStack, $security);
    }

    public function listenAction(Request $request): Response
    {
        $content = $request->getContent();
        $data    = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !$this->validatePayload($data)) {
            return new JsonResponse(['error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        $this->contactManager->processAndGenerateContact($data);

        return new Response();
    }

    private function validatePayload(mixed $data): bool
    {
        if (
            !empty($data['Id']) &&
            isset($data['Event Type']) && $data['Event Type'] === 'lead' &&
            isset($data['Data']['Attendee Details']) &&
            !empty($data['Data']['Attendee Details']['Name']) &&
            !empty($data['Data']['Attendee Details']['Email Address']) &&
            !empty($data['Data']['Attendee Details']['Booking Id'])
        ) {
            return true;
        }

        return false;
    }
}
