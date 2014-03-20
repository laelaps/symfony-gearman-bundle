<?php

namespace Laelaps\GearmanBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Job
 *
 * @ORM\Table()
 * @ORM\Entity
 */
class Job
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="startTime", type="datetime")
     */
    private $startTime;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="endTime", type="datetime", nullable=true)
     */
    private $endTime;

    /**
     * @var string
     *
     * @ORM\Column(name="returnStatus", type="string", length=255, nullable=true)
     */
    private $returnStatus;

    /**
     * @var string
     *
     * @ORM\Column(name="output", type="text", nullable=true)
     */
    private $output;

    /**
     * @var string
     *
     * @ORM\Column(name="errorOutput", type="text", nullable=true)
     */
    private $errorOutput;


    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return Job
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set startTime
     *
     * @param \DateTime $startTime
     *
     * @return Job
     */
    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;

        return $this;
    }

    /**
     * Get startTime
     *
     * @return \DateTime
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * Set endTime
     *
     * @param \DateTime $endTime
     *
     * @return Job
     */
    public function setEndTime($endTime)
    {
        $this->endTime = $endTime;

        return $this;
    }

    /**
     * Get endTime
     *
     * @return \DateTime
     */
    public function getEndTime()
    {
        return $this->endTime;
    }

    /**
     * Set returnStatus
     *
     * @param string $returnStatus
     *
     * @return Job
     */
    public function setReturnStatus($returnStatus)
    {
        $this->returnStatus = $returnStatus;

        return $this;
    }

    /**
     * Get returnStatus
     *
     * @return string
     */
    public function getReturnStatus()
    {
        return $this->returnStatus;
    }

    /**
     * Set output
     *
     * @param string $output
     *
     * @return Job
     */
    public function setOutput($output)
    {
        $this->output = $output;

        return $this;
    }

    /**
     * Get output
     *
     * @return string
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Set errorOutput
     *
     * @param string $errorOutput
     *
     * @return Job
     */
    public function setErrorOutput($errorOutput)
    {
        $this->errorOutput = $errorOutput;

        return $this;
    }

    /**
     * Get errorOutput
     *
     * @return string
     */
    public function getErrorOutput()
    {
        return $this->errorOutput;
    }
}

