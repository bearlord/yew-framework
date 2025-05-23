<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Server\Beans\Http;

use Psr\Http\Message\StreamInterface;

class HttpStream implements StreamInterface
{

    /**
     * @var string
     */
    protected string $contents;

    /**
     * @var int|null
     */
    protected ?int $size;

    /**
     * SwooleStream constructor.
     *
     * @param string $contents
     */
    public function __construct(string $contents = '')
    {
        $this->contents = $contents;

        $this->size = strlen($this->contents);
    }

    /**
     * Reads all data from the stream into a string, from the beginning to end.
     * This method MUST attempt to seek to the beginning of the stream before
     * reading data and read the stream until the end is reached.
     * Warning: This could attempt to load a large amount of data into memory.
     * This method MUST NOT raise an exception in order to conform with PHP's
     * string casting operations.
     *
     * @see http://php.net/manual/en/language.oop5.magic.php#object.tostring
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->getContents();
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Closes the stream and any underlying resources.
     *
     * @return void
     */
    public function close()
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * Separates any underlying resources from the stream.
     * After the stream has been detached, the stream is in an unusable state.
     *
     * @return void Underlying PHP stream, if any
     */
    public function detach()
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * Get the size of the stream if known.
     *
     * @return int|null Returns the size in bytes if known, or null if unknown.
     */
    public function getSize(): ?int
    {
        if (!$this->size) {
            $this->size = strlen($this->getContents());
        }
        return $this->size;
    }

    /**
     * Returns the current position of the file read/write pointer
     *
     * @return void Position of the file pointer
     */
    public function tell()
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * Returns true if the stream is at the end of the stream.
     *
     */
    public function eof()
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * Returns whether the stream is seekable.
     *
     */
    public function isSeekable()
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * Seek to a position in the stream.
     *
     * @link http://www.php.net/manual/en/function.fseek.php
     * @param int $offset Stream offset
     * @param int|null $whence Specifies how the cursor position will be calculated
     *     based on the seek offset. Valid values are identical to the built-in
     *     PHP $whence values for `fseek()`.  SEEK_SET: Set position equal to
     *     offset bytes SEEK_CUR: Set position to current location plus offset
     *     SEEK_END: Set position to end-of-stream plus offset.
     * @throws \RuntimeException on failure.
     */
    public function seek(int $offset, ?int $whence = SEEK_SET)
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * Seek to the beginning of the stream.
     * If the stream is not seekable, this method will raise an exception;
     * otherwise, it will perform a seek(0).
     *
     * @throws \RuntimeException on failure.
     * @link http://www.php.net/manual/en/function.fseek.php
     * @see seek()
     */
    public function rewind()
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * Returns whether the stream is writable.
     *
     * @return bool
     */
    public function isWritable(): bool
    {
        return false;
    }

    /**
     * Write data to the stream.
     *
     * @param string $string The string that is to be written.
     * @throws \RuntimeException on failure.
     */
    public function write(string $string)
    {
        $this->contents .= $string;
    }

    /**
     * Returns whether the stream is readable.
     *
     * @return bool
     */
    public function isReadable(): bool
    {
        return true;
    }

    /**
     * Read data from the stream.
     *
     * @param int $length Read up to $length bytes from the object and return them.
     *     Fewer than $length bytes may be returned if underlying stream
     *     call returns fewer bytes.
     * @return string Returns the data read from the stream, or an empty string
     *     if no bytes are available.
     * @throws \RuntimeException if an error occurs.
     */
    public function read(int $length): string
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * Returns the remaining contents in a string
     *
     * @return string
     * @throws \RuntimeException if unable to read or an error occurs while
     *     reading.
     */
    public function getContents(): string
    {
        return $this->contents;
    }

    /**
     * Get stream metadata as an associative array or retrieve a specific key.
     * The keys returned are identical to the keys returned from PHP's
     * stream_get_meta_data() function.
     *
     * @link http://php.net/manual/en/function.stream-get-meta-data.php
     * @param string|null $key Specific metadata to retrieve.
     * @return array|mixed|null Returns an associative array if no key is
     *     provided. Returns a specific key value if a key is provided and the
     *     value is found, or null if the key is not found.
     */
    public function getMetadata(string $key = null)
    {
        throw new \BadMethodCallException('Not implemented');
    }
}
