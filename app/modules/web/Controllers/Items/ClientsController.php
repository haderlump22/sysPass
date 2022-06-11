<?php
/*
 * sysPass
 *
 * @author nuxsmin
 * @link https://syspass.org
 * @copyright 2012-2022, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Modules\Web\Controllers\Items;

use Klein\Klein;
use SP\Core\Acl\Acl;
use SP\Core\Application;
use SP\Core\PhpExtensionChecker;
use SP\Core\UI\ThemeInterface;
use SP\Domain\Client\ClientServiceInterface;
use SP\Http\Json;
use SP\Http\RequestInterface;
use SP\Modules\Web\Controllers\SimpleControllerBase;
use SP\Mvc\View\Components\SelectItemAdapter;

/**
 * Class ClientsController
 */
final class ClientsController extends SimpleControllerBase
{
    private ClientServiceInterface $clientService;

    public function __construct(
        Application $application,
        ThemeInterface $theme,
        Klein $router,
        Acl $acl,
        RequestInterface $request,
        PhpExtensionChecker $extensionChecker,
        ClientServiceInterface $clientService
    ) {
        parent::__construct($application, $theme, $router, $acl, $request, $extensionChecker);

        $this->checks();

        $this->clientService = $clientService;
    }

    /**
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     * @throws \SP\Core\Exceptions\SPException
     */
    public function clientsAction(): void
    {
        Json::factory($this->router->response())
            ->returnRawJson(SelectItemAdapter::factory($this->clientService->getAllForUser())->getJsonItemsFromModel());
    }
}