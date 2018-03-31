<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      https://syspass.org
 * @copyright 2012-2018, Rubén Domínguez nuxsmin@$syspass.org
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
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Services\User;

use SP\Core\Crypt\Hash;
use SP\Core\Exceptions\SPException;
use SP\DataModel\ItemSearchData;
use SP\DataModel\UserData;
use SP\DataModel\UserPreferencesData;
use SP\Repositories\User\UserRepository;
use SP\Services\Service;
use SP\Services\ServiceException;
use SP\Services\ServiceItemTrait;
use SP\Util\Util;

/**
 * Class UserService
 *
 * @package SP\Services\User
 */
class UserService extends Service
{
    use ServiceItemTrait;

    /**
     * @var UserRepository
     */
    protected $userRepository;
    /**
     * @var UserPassService
     */
    protected $userPassService;

    /**
     * @param UserData $userData
     * @return UserLoginResponse
     */
    public static function mapUserLoginResponse(UserData $userData)
    {
        return (new UserLoginResponse())->setId($userData->getId())
            ->setName($userData->getName())
            ->setLogin($userData->getLogin())
            ->setSsoLogin($userData->getSsoLogin())
            ->setEmail($userData->getEmail())
            ->setPass($userData->getPass())
            ->setHashSalt($userData->getHashSalt())
            ->setMPass($userData->getMPass())
            ->setMKey($userData->getMKey())
            ->setLastUpdateMPass($userData->getLastUpdateMPass())
            ->setUserGroupId($userData->getUserGroupId())
            ->setUserGroupName($userData->getUserGroupName())
            ->setUserProfileId($userData->getUserProfileId())
            ->setPreferences(self::getUserPreferences($userData->getPreferences()))
            ->setIsLdap($userData->isLdap())
            ->setIsAdminAcc($userData->isAdminAcc())
            ->setIsAdminApp($userData->isAdminApp())
            ->setIsMigrate($userData->isMigrate())
            ->setIsChangedPass($userData->isIsChangedPass())
            ->setIsChangePass($userData->isChangePass())
            ->setIsDisabled($userData->isDisabled());
    }

    /**
     * Returns user's preferences object
     *
     * @param string $preferences
     * @return UserPreferencesData
     */
    public static function getUserPreferences($preferences)
    {
        if (!empty($preferences)) {
            return Util::unserialize(UserPreferencesData::class, $preferences, 'SP\UserPreferences');
        }

        return new UserPreferencesData();
    }

    /**
     * Actualiza el último inicio de sesión del usuario en la BBDD.
     *
     * @param $id int El id del usuario
     * @return bool
     * @throws \SP\Core\Exceptions\QueryException
     * @throws \SP\Core\Exceptions\ConstraintException
     */
    public function updateLastLoginById($id)
    {
        return $this->userRepository->updateLastLoginById($id);
    }

    /**
     * @param $login
     * @return bool
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function checkExistsByLogin($login)
    {
        return $this->userRepository->checkExistsByLogin($login);
    }

    /**
     * Returns the item for given id
     *
     * @param int $id
     * @return mixed
     * @throws SPException
     */
    public function getById($id)
    {
        return $this->userRepository->getById($id);
    }

    /**
     * Returns the item for given id
     *
     * @param $login
     * @return UserData
     * @throws SPException
     */
    public function getByLogin($login)
    {
        return $this->userRepository->getByLogin($login);
    }

    /**
     * Deletes an item
     *
     * @param $id
     * @return UserService
     * @throws SPException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function delete($id)
    {
        if ($this->userRepository->delete($id) === 0) {
            throw new ServiceException(__u('Usuario no encontrado'), ServiceException::INFO);
        }

        return $this;
    }

    /**
     * @param array $ids
     * @return int
     * @throws ServiceException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function deleteByIdBatch(array $ids)
    {
        if (($count = $this->userRepository->deleteByIdBatch($ids)) !== count($ids)) {
            throw new ServiceException(__u('Error al eliminar los usuarios'), ServiceException::WARNING);
        }

        return $count;
    }

    /**
     * Creates an item
     *
     * @param UserLoginRequest $userLoginRequest
     * @return mixed
     * @throws SPException
     */
    public function createOnLogin(UserLoginRequest $userLoginRequest)
    {
        $userData = new UserData();
        $userData->setLogin($userLoginRequest->getLogin());
        $userData->setName($userLoginRequest->getName());
        $userData->setEmail($userLoginRequest->getEmail());
        $userData->setIsLdap($userLoginRequest->getisLdap());
        $userData->setPass($userLoginRequest->getPassword());

        return $this->create($userData);
    }

    /**
     * Creates an item
     *
     * @param UserData $itemData
     * @return int
     * @throws SPException
     */
    public function create($itemData)
    {
        $itemData->setPass(Hash::hashKey($itemData->getPass()));

        return $this->userRepository->create($itemData);
    }

    /**
     * Creates an item
     *
     * @param UserData $itemData
     * @param string   $userPass
     * @param string   $masterPass
     * @return int
     * @throws SPException
     * @throws \Defuse\Crypto\Exception\CryptoException
     */
    public function createWithMasterPass($itemData, $userPass, $masterPass)
    {
        $response = $this->userPassService->createMasterPass($masterPass, $itemData->getLogin(), $userPass);

        $itemData->setPass(Hash::hashKey($userPass));
        $itemData->setMPass($response->getCryptMasterPass());
        $itemData->setMKey($response->getCryptSecuredKey());
        $itemData->setLastUpdateMPass(time());

        return $this->userRepository->create($itemData);
    }

    /**
     * Searches for items by a given filter
     *
     * @param ItemSearchData $SearchData
     * @return array
     */
    public function search(ItemSearchData $SearchData)
    {
        return $this->userRepository->search($SearchData);
    }

    /**
     * Updates an item
     *
     * @param UserData $itemData
     * @return mixed
     * @throws SPException
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function update($itemData)
    {
        return $this->userRepository->update($itemData);
    }

    /**
     * Updates an user's pass
     *
     * @param int    $userId
     * @param string $pass
     * @return bool
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function updatePass($userId, $pass)
    {
        $passRequest = new UpdatePassRequest(Hash::hashKey($pass));
        $passRequest->setIsChangePass(0);
        $passRequest->setIsChangedPass(1);

        return $this->userRepository->updatePassById($userId, $passRequest);
    }

    /**
     * @param                     $userId
     * @param UserPreferencesData $userPreferencesData
     * @return bool
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function updatePreferencesById($userId, UserPreferencesData $userPreferencesData)
    {
        return $this->userRepository->updatePreferencesById($userId, $userPreferencesData);
    }

    /**
     * @param UserLoginRequest $userLoginRequest
     * @return bool
     * @throws \SP\Core\Exceptions\ConstraintException
     * @throws \SP\Core\Exceptions\QueryException
     */
    public function updateOnLogin(UserLoginRequest $userLoginRequest)
    {
        $userData = new UserData();
        $userData->setLogin($userLoginRequest->getLogin());
        $userData->setName($userLoginRequest->getName());
        $userData->setEmail($userLoginRequest->getEmail());
        $userData->setIsLdap($userLoginRequest->getisLdap());
        $userData->setPass(Hash::hashKey($userLoginRequest->getPassword()));

        return $this->userRepository->updateOnLogin($userData);
    }

    /**
     * Get all items from the service's repository
     *
     * @return array
     */
    public function getAllBasic()
    {
        return $this->userRepository->getBasicInfo();
    }

    /**
     * Obtener el email de los usuarios de un grupo
     *
     * @param $groupId
     * @return array
     */
    public function getUserEmailForGroup($groupId)
    {
        return $this->userRepository->getUserEmailForGroup($groupId);
    }

    /**
     * Returns the usage of the given user's id
     *
     * @param int $id
     * @return array
     */
    public function getUsageForUser($id)
    {
        return $this->userRepository->getUsageForUser($id);
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function initialize()
    {
        $this->userRepository = $this->dic->get(UserRepository::class);
        $this->userPassService = $this->dic->get(UserPassService::class);
    }
}