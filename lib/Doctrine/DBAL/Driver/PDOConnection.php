<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Driver;

use Doctrine\DBAL\ParameterType;
use PDO;

/**
 * PDO implementation of the Connection interface.
 * Used by all PDO-based drivers.
 *
 * @since 2.0
 */
class PDOConnection extends PDO implements Connection, ServerInfoAwareConnection
{
    /**
     * @var string
     */
    private $lastInsertId = '0';

    /**
     * @param string      $dsn
     * @param string|null $user
     * @param string|null $password
     * @param array|null  $options
     *
     * @throws PDOException in case of an error.
     */
    public function __construct($dsn, $user = null, $password = null, array $options = null)
    {
        try {
            parent::__construct($dsn, $user, $password, $options);
            $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, ['Doctrine\DBAL\Driver\PDOStatement', [$this]]);
            $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exec($statement)
    {
        try {
            $result = parent::exec($statement);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }

        $this->lastInsertId(); // Keep track of the last insert ID.

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getServerVersion()
    {
        return PDO::getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    /**
     * {@inheritdoc}
     */
    public function prepare($prepareString, $driverOptions = [])
    {
        try {
            return parent::prepare($prepareString, $driverOptions);
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function query()
    {
        $args = func_get_args();
        $argsCount = count($args);

        try {
            if ($argsCount == 4) {
                $stmt = parent::query($args[0], $args[1], $args[2], $args[3]);

                $this->lastInsertId(); // Keep track of the last insert ID.

                return $stmt;
            }

            if ($argsCount == 3) {
                $stmt = parent::query($args[0], $args[1], $args[2]);

                $this->lastInsertId(); // Keep track of the last insert ID.

                return $stmt;
            }

            if ($argsCount == 2) {
                $stmt = parent::query($args[0], $args[1]);

                $this->lastInsertId(); // Keep track of the last insert ID.

                return $stmt;
            }

            $stmt = parent::query($args[0]);


            $this->lastInsertId(); // Keep track of the last insert ID.

            return $stmt;
        } catch (\PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function quote($input, $type = ParameterType::STRING)
    {
        return parent::quote($input, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId($name = null)
    {
        if (null !== $name) {
            return parent::lastInsertId($name);
        }

        // We need to avoid unnecessary exception generation for drivers not supporting this feature,
        // by temporarily disabling exception mode.
        $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);

        $lastInsertId = parent::lastInsertId($name);

        // Reactivate exception mode.
        $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        if (null === $lastInsertId) {
            // In case this driver implementation does not support this feature
            // or an error occurred while retrieving the last insert ID, simply return the last tracked insert ID.
            return $this->lastInsertId;
        }

        // The last insert ID is reset to "0" in certain situations by some implementations,
        // therefore we keep the previously set insert ID locally.
        if ('0' !== $lastInsertId) {
            $this->lastInsertId = $lastInsertId;
        }

        return $this->lastInsertId;
    }

    /**
     * {@inheritdoc}
     */
    public function requiresQueryForServerVersion()
    {
        return false;
    }
}
