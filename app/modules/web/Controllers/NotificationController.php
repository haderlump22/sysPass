<?php
/*
 * sysPass
 *
 * @author nuxsmin
 * @link https://syspass.org
 * @copyright 2012-2021, Rubén Domínguez nuxsmin@$syspass.org
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

namespace SP\Modules\Web\Controllers;

use DI\DependencyException;
use DI\NotFoundException;
use Exception;
use SP\Core\Acl\Acl;
use SP\Core\Acl\ActionsInterface;
use SP\Core\Events\Event;
use SP\Core\Events\EventMessage;
use SP\Core\Exceptions\ConstraintException;
use SP\Core\Exceptions\QueryException;
use SP\Core\Exceptions\SessionTimeout;
use SP\Core\Exceptions\SPException;
use SP\DataModel\NotificationData;
use SP\Domain\Auth\Services\AuthException;
use SP\Domain\Notification\Services\NotificationService;
use SP\Domain\User\Services\UserService;
use SP\Html\DataGrid\DataGridInterface;
use SP\Http\JsonResponse;
use SP\Infrastructure\Common\Repositories\NoSuchItemException;
use SP\Modules\Web\Controllers\Helpers\Grid\NotificationGrid;
use SP\Modules\Web\Controllers\Traits\JsonTrait;
use SP\Modules\Web\Forms\NotificationForm;
use SP\Mvc\Controller\CrudControllerInterface;
use SP\Mvc\Controller\ItemTrait;
use SP\Mvc\View\Components\SelectItemAdapter;

/**
 * Class NotificationController
 *
 * @package SP\Modules\Web\Controllers
 */
final class NotificationController extends ControllerBase implements CrudControllerInterface
{
    use JsonTrait, ItemTrait;

    protected ?NotificationService $notificationService = null;

    /**
     * indexAction
     *
     * @throws DependencyException
     * @throws NotFoundException
     * @throws ConstraintException
     * @throws QueryException
     * @throws SPException
     */
    public function indexAction(): void
    {
        if (!$this->acl->checkUserAccess(ActionsInterface::NOTIFICATION)) {
            return;
        }

        $this->view->addTemplate('index');

        $this->view->assign('data', $this->getSearchGrid());

        $this->view();
    }

    /**
     * getSearchGrid
     *
     * @throws DependencyException
     * @throws NotFoundException
     * @throws ConstraintException
     * @throws QueryException
     */
    protected function getSearchGrid(): DataGridInterface
    {
        $itemSearchData = $this->getSearchData(
            $this->configData->getAccountCount(),
            $this->request
        );

        $notificationGrid = $this->dic->get(NotificationGrid::class);

        return $notificationGrid->updatePager(
            $notificationGrid->getGrid($this->notificationService->search($itemSearchData)),
            $itemSearchData
        );
    }

    /**
     * View action
     *
     * @param int $id
     *
     * @return bool
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \JsonException
     */
    public function viewAction(int $id): bool
    {
        try {
            if (!$this->acl->checkUserAccess(ActionsInterface::NOTIFICATION_VIEW)) {
                return $this->returnJsonResponse(
                    JsonResponse::JSON_ERROR,
                    __u('You don\'t have permission to do this operation')
                );
            }

            $this->view->assign('header', __('View Notification'));
            $this->view->assign('isView', true);

            $this->setViewData($id);

            $this->eventDispatcher->notifyEvent(
                'show.notification',
                new Event($this)
            );

            return $this->returnJsonResponseData(['html' => $this->render()]);
        } catch (Exception $e) {
            processException($e);

            $this->eventDispatcher->notifyEvent(
                'exception',
                new Event($e)
            );

            return $this->returnJsonResponseException($e);
        }
    }

    /**
     * Sets view data for displaying notification's data
     *
     * @param int|null $notificationId
     *
     * @throws ConstraintException
     * @throws QueryException
     * @throws NoSuchItemException
     */
    protected function setViewData(?int $notificationId = null): void
    {
        $this->view->addTemplate('notification');

        $notification = $notificationId
            ? $this->notificationService->getById($notificationId)
            : new NotificationData();

        $this->view->assign('notification', $notification);

        if ($this->userData->getIsAdminApp()) {
            $this->view->assign(
                'users',
                SelectItemAdapter::factory(UserService::getItemsBasic())
                    ->getItemsFromModelSelected([$notification->userId])
            );
        }

        $this->view->assign(
            'nextAction',
            Acl::getActionRoute(ActionsInterface::NOTIFICATION)
        );

        if ($this->view->isView === true) {
            $this->view->assign('disabled', 'disabled');
            $this->view->assign('readonly', 'readonly');
        } else {
            $this->view->assign('disabled', false);
            $this->view->assign('readonly', false);
        }
    }

    /**
     * @return bool
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \JsonException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function searchAction(): bool
    {
        if (!$this->acl->checkUserAccess(ActionsInterface::NOTIFICATION_SEARCH)) {
            return $this->returnJsonResponse(
                JsonResponse::JSON_ERROR,
                __u('You don\'t have permission to do this operation')
            );
        }

        $this->view->addTemplate('datagrid-table', 'grid');
        $this->view->assign('data', $this->getSearchGrid());

        return $this->returnJsonResponseData(['html' => $this->render()]);
    }

    /**
     * @return bool
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \JsonException
     */
    public function createAction(): bool
    {
        try {
            if (!$this->acl->checkUserAccess(ActionsInterface::NOTIFICATION_CREATE)) {
                return $this->returnJsonResponse(
                    JsonResponse::JSON_ERROR,
                    __u('You don\'t have permission to do this operation')
                );
            }

            $this->view->assign('header', __('New Notification'));
            $this->view->assign('isView', false);
            $this->view->assign('route', 'notification/saveCreate');

            $this->setViewData();

            $this->eventDispatcher->notifyEvent(
                'show.notification.create',
                new Event($this)
            );

            return $this->returnJsonResponseData(['html' => $this->render()]);
        } catch (Exception $e) {
            processException($e);

            $this->eventDispatcher->notifyEvent(
                'exception',
                new Event($e)
            );

            return $this->returnJsonResponseException($e);
        }
    }

    /**
     * Edit action
     *
     * @param int $id
     *
     * @return bool
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \JsonException
     */
    public function editAction(int $id): bool
    {
        try {
            if (!$this->acl->checkUserAccess(ActionsInterface::NOTIFICATION_EDIT)) {
                return $this->returnJsonResponse(
                    JsonResponse::JSON_ERROR,
                    __u('You don\'t have permission to do this operation')
                );
            }

            $this->view->assign('header', __('Edit Notification'));
            $this->view->assign('isView', false);
            $this->view->assign('route', 'notification/saveEdit/' . $id);

            $this->setViewData($id);

            $this->eventDispatcher->notifyEvent(
                'show.notification.edit',
                new Event($this)
            );

            return $this->returnJsonResponseData(['html' => $this->render()]);
        } catch (Exception $e) {
            processException($e);

            $this->eventDispatcher->notifyEvent(
                'exception',
                new Event($e)
            );

            return $this->returnJsonResponseException($e);
        }
    }

    /**
     * Delete action
     *
     * @param int|null $id
     *
     * @return bool
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \JsonException
     */
    public function deleteAction(?int $id = null): bool
    {
        try {
            if ($id === null) {
                if ($this->userData->getIsAdminApp()) {
                    $this->notificationService->deleteAdminBatch($this->getItemsIdFromRequest($this->request));
                } else {
                    $this->notificationService->deleteByIdBatch($this->getItemsIdFromRequest($this->request));
                }

                $this->eventDispatcher->notifyEvent(
                    'delete.notification.selection',
                    new Event(
                        $this,
                        EventMessage::factory()
                            ->addDescription(__u('Notifications deleted'))
                    )
                );

                return $this->returnJsonResponse(
                    JsonResponse::JSON_SUCCESS,
                    __u('Notifications deleted')
                );
            }

            if ($this->userData->getIsAdminApp()) {
                $this->notificationService->deleteAdmin($id);
            } else {
                $this->notificationService->delete($id);
            }

            $this->eventDispatcher->notifyEvent(
                'delete.notification',
                new Event(
                    $this,
                    EventMessage::factory()
                        ->addDescription(__u('Notification deleted'))
                        ->addDetail(__u('Notification'), $id)
                )
            );

            return $this->returnJsonResponse(
                JsonResponse::JSON_SUCCESS,
                __u('Notification deleted')
            );
        } catch (Exception $e) {
            processException($e);

            $this->eventDispatcher->notifyEvent(
                'exception',
                new Event($e)
            );

            return $this->returnJsonResponseException($e);
        }
    }

    /**
     * Check action
     *
     * @param int $id
     *
     * @return bool
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \JsonException
     */
    public function checkAction(int $id): bool
    {
        try {
            if (!$this->acl->checkUserAccess(ActionsInterface::NOTIFICATION_CHECK)) {
                return $this->returnJsonResponse(
                    JsonResponse::JSON_ERROR,
                    __u('You don\'t have permission to do this operation')
                );
            }

            $this->notificationService->setCheckedById($id);

            $this->eventDispatcher->notifyEvent(
                'check.notification',
                new Event(
                    $this,
                    EventMessage::factory()
                        ->addDescription(__u('Notification read'))
                        ->addDetail(__u('Notification'), $id)
                )
            );

            return $this->returnJsonResponse(
                JsonResponse::JSON_SUCCESS,
                __u('Notification read')
            );
        } catch (Exception $e) {
            processException($e);

            $this->eventDispatcher->notifyEvent(
                'exception',
                new Event($e)
            );

            return $this->returnJsonResponseException($e);
        }
    }

    /**
     * @return bool
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \JsonException
     */
    public function saveCreateAction(): bool
    {
        try {
            if (!$this->acl->checkUserAccess(ActionsInterface::NOTIFICATION_CREATE)) {
                return $this->returnJsonResponse(
                    JsonResponse::JSON_ERROR,
                    __u('You don\'t have permission to do this operation')
                );
            }

            $form = new NotificationForm($this->dic);
            $form->validateFor(ActionsInterface::NOTIFICATION_CREATE, null);

            $this->notificationService->create($form->getItemData());

            $this->eventDispatcher->notifyEvent(
                'create.notification',
                new Event(
                    $this,
                    EventMessage::factory()
                        ->addDescription(__u('Notification created'))
                )
            );

            return $this->returnJsonResponse(
                JsonResponse::JSON_SUCCESS,
                __u('Notification created')
            );
        } catch (Exception $e) {
            processException($e);

            $this->eventDispatcher->notifyEvent(
                'exception',
                new Event($e)
            );

            return $this->returnJsonResponseException($e);
        }
    }

    /**
     * Saves edit action
     *
     * @param int $id
     *
     * @return bool
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \JsonException
     */
    public function saveEditAction(int $id): bool
    {
        try {
            if (!$this->acl->checkUserAccess(ActionsInterface::NOTIFICATION_EDIT)) {
                return $this->returnJsonResponse(
                    JsonResponse::JSON_ERROR,
                    __u('You don\'t have permission to do this operation')
                );
            }

            $form = new NotificationForm($this->dic, $id);
            $form->validateFor(ActionsInterface::NOTIFICATION_EDIT, null);

            $this->notificationService->update($form->getItemData());

            $this->eventDispatcher->notifyEvent(
                'edit.notification',
                new Event(
                    $this,
                    EventMessage::factory()
                        ->addDescription(__u('Notification updated'))
                )
            );

            return $this->returnJsonResponse(
                JsonResponse::JSON_SUCCESS,
                __u('Notification updated')
            );
        } catch (Exception $e) {
            processException($e);

            $this->eventDispatcher->notifyEvent(
                'exception',
                new Event($e)
            );

            return $this->returnJsonResponseException($e);
        }
    }

    /**
     * @throws AuthException
     * @throws DependencyException
     * @throws NotFoundException
     * @throws SessionTimeout
     */
    protected function initialize(): void
    {
        $this->checkLoggedIn();

        $this->notificationService = $this->dic->get(NotificationService::class);
    }
}