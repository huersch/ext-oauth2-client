<?php

declare(strict_types=1);

/*
 * This file is part of the OAuth2 Client extension for TYPO3
 * - (c) 2021 Waldhacker UG
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Waldhacker\Oauth2Client\Controller;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;
use Waldhacker\Oauth2Client\Repository\BackendUserRepository;
use Waldhacker\Oauth2Client\Service\Oauth2ProviderManager;

class ShowBackendModule extends AbstractBackendController
{
    protected ModuleTemplate $moduleTemplate;
    protected UriBuilder $uriBuilder;
    private BackendUserRepository $backendUserRepository;
    private Oauth2ProviderManager $oauth2ProviderManager;
    private ResponseFactoryInterface $responseFactory;

    public function __construct(
        ModuleTemplate $moduleTemplate,
        UriBuilder $uriBuilder,
        BackendUserRepository $backendUserRepository,
        Oauth2ProviderManager $oauth2ProviderManager,
        ResponseFactoryInterface $responseFactory
    ) {
        $this->moduleTemplate = $moduleTemplate;
        $this->uriBuilder = $uriBuilder;
        $this->backendUserRepository = $backendUserRepository;
        $this->oauth2ProviderManager = $oauth2ProviderManager;
        $this->responseFactory = $responseFactory;
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $this->addButtons($request);

        $view = $this->initializeView('Register');
        $view->assignMultiple([
            'providers' => $this->oauth2ProviderManager->getConfiguredProviders(),
            'activeProviders' => $this->backendUserRepository->getActiveProviders()
        ]);
        $this->moduleTemplate->setContent($view->render());
        $this->moduleTemplate->getPageRenderer()->addJsFile('EXT:oauth2_client/Resources/Public/JavaScript/register.js');
        $response = $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
        $response->getBody()->write($this->moduleTemplate->renderContent());
        return $response;
    }

    private function addButtons(ServerRequestInterface $request): void
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();

        if (($returnUrl = $this->getReturnUrl($request)) !== '') {
            $button = $buttonBar
                ->makeLinkButton()
                ->setHref($returnUrl)
                ->setIcon($this->moduleTemplate->getIconFactory()->getIcon('actions-view-go-back', Icon::SIZE_SMALL))
                ->setTitle($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.goBack'))
                ->setShowLabelText(true);
            $buttonBar->addButton($button);
        }

        $reloadButton = $buttonBar
            ->makeLinkButton()
            ->setHref($request->getAttribute('normalizedParams')->getRequestUri())
            ->setTitle($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.reload'))
            ->setIcon($this->moduleTemplate->getIconFactory()->getIcon('actions-refresh', Icon::SIZE_SMALL));
        $buttonBar->addButton($reloadButton, ButtonBar::BUTTON_POSITION_RIGHT);
    }

    protected function initializeView(string $templateName): ViewInterface
    {
        /** @var StandaloneView $view */
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplateRootPaths(['EXT:oauth2_client/Resources/Private/Templates/Backend']);
        $view->setPartialRootPaths(['EXT:backend/Resources/Private/Partials']);
        $view->setLayoutRootPaths(['EXT:backend/Resources/Private/Layouts']);
        $view->setTemplate($templateName);
        return $view;
    }

    protected function getReturnUrl(ServerRequestInterface $request): string
    {
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();
        if (is_array($queryParams) && isset($queryParams['returnUrl'])) {
            $returnUrl = $queryParams['returnUrl'];
        } elseif (is_array($parsedBody) && isset($parsedBody['returnUrl'])) {
            $returnUrl = $parsedBody['returnUrl'];
        } else {
            $returnUrl = '';
        }
        $returnUrl = GeneralUtility::sanitizeLocalUrl($returnUrl);

        if ($returnUrl === '' && ExtensionManagementUtility::isLoaded('setup')) {
            $returnUrl = (string)$this->uriBuilder->buildUriFromRoute('user_setup');
        }

        return $returnUrl;
    }
}
