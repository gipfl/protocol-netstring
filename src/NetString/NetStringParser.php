<?php

namespace gipfl\Protocol\NetString;

use Evenement\EventEmitterTrait;
use gipfl\Protocol\Exception\ProtocolError;

class NetStringParser
{
    use EventEmitterTrait;

    protected $buffer = '';
    protected $bufferLength = 0;
    protected $bufferOffset = 0;
    protected $expectedLength;

    /**
     * @param $data
     */
    public function feed($data)
    {
        $this->buffer .= $data;
        $this->bufferLength += \strlen($data);
        while ($this->bufferHasPacket()) {
            $this->processNextPacket();

            if ($this->bufferOffset !== 0) {
                $this->buffer = \substr($this->buffer, $this->bufferOffset);
                $this->bufferOffset = 0;
                $this->bufferLength = \strlen($this->buffer);
            }
        }
    }

    /**
     * @return bool
     */
    protected function bufferHasPacket()
    {
        if ($this->expectedLength === null) {
            if (false !== ($pos = \strpos(\substr($this->buffer, $this->bufferOffset, 10), ':'))) {
                $lengthString = \ltrim(\substr($this->buffer, $this->bufferOffset, $pos), ',');
                if (! \ctype_digit($lengthString)) {
                    $this->emit('error', [
                        new ProtocolError("Invalid length $lengthString")
                    ]);
                    $this->removeAllListeners();

                    return false;
                }
                $this->expectedLength = (int) $lengthString;
                $this->bufferOffset = $pos + 1;
            } elseif ($this->bufferLength > ($this->bufferOffset + 10)) {
                $this->throwInvalidBuffer();
                return false;
            } else {
                return false;
            }
        }

        return $this->bufferLength > ($this->bufferOffset + $this->expectedLength);
    }

    protected function processNextPacket()
    {
        $packet = \substr($this->buffer, $this->bufferOffset, $this->expectedLength);

        $this->bufferOffset = $this->bufferOffset + $this->expectedLength;
        $this->expectedLength = null;

        $this->emit('packet', [$packet]);
    }
    
    protected function throwInvalidBuffer()
    {
        $len = \strlen($this->buffer);
        if ($len < 200) {
            $debug = $this->buffer;
        } else {
            $debug = \substr($this->buffer, 0, 100)
                . \sprintf('[..] truncated %d bytes [..] ', $len)
                . \substr($this->buffer, -100);
        }

        $this->emit('error', [
            new ProtocolError(
                'Got invalid NetString data: %s',
                $debug
            )
        ]);
    }
}
