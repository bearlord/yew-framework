<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Ipc;

class IpcResultData
{
    /**
     * @var int
     */
    private int $token;
    /**
     * @var mixed
     */
    private $result;
    /**
     * @var string|null
     */
    private ?string $errorClass;
    /**
     * @var int|null
     */
    private ?int $errorCode;
    /**
     * @var string|null
     */
    private ?string $errorMessage;

    /**
     *
     * @param int $token
     * @param $result
     * @param string|null $errorClass
     * @param int|null $errorCode
     * @param string|null $errorMessage
     */
    public function __construct(int $token, $result, ?string $errorClass = null, ?int $errorCode = null, ?string $errorMessage = null)
    {
        $this->token = $token;
        $this->result = $result;
        $this->errorClass = $errorClass;
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
    }

    /**
     * @return int
     */
    public function getToken(): int
    {
        return $this->token;
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @return string
     */
    public function getErrorClass(): ?string
    {
        return $this->errorClass;
    }

    /**
     * @return int
     */
    public function getErrorCode(): ?int
    {
        return $this->errorCode;
    }

    /**
     * @return string
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

}