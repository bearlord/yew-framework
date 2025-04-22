<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Coroutine\Beans;

/**
 * Class ChannelStats
 * @package Yew\Coroutine\Beans
 */
class ChannelStats
{
    /**
     * Number of consumers, indicating that the current channel is empty and
     * there are N coroutines waiting for other coroutines to call the push method to produce data
     * @var int
     */
    private $consumerNum;

    /**
     * Number of producers, indicating that the current channel is full, and there are N coroutines
     * waiting for other coroutines to call the pop method to consume data
     * @var int
     */
    private $producerNum;

    /**
     * Number of elements in the channel
     * @var int
     */
    private $queueNum;

    /**
     * ChannelStats constructor.
     * @param $data
     */
    public function __construct($data)
    {
        $this->consumerNum = $data['consumer_num'];
        $this->producerNum = $data['producer_num'];
        $this->queueNum = $data['queue_num'];
    }

    /**
     * @return int
     */
    public function getConsumerNum(): int
    {
        return $this->consumerNum;
    }

    /**
     * @return int
     */
    public function getProducerNum(): int
    {
        return $this->producerNum;
    }

    /**
     * @return int
     */
    public function getQueueNum(): int
    {
        return $this->queueNum;
    }
}