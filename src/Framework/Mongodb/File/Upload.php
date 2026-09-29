<?php
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */

namespace Yew\Framework\Mongodb\File;

use MongoDB\BSON\Binary;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\UTCDatetime;
use MongoDB\Driver\Exception\InvalidArgumentException;
use Yew\Framework\Exception\InvalidParamException;
use Yew\Framework\Base\BaseObject;
use Yew\Framework\Helpers\StringHelper;

/**
 * Upload represents the GridFS upload operation.
 *
 * An `Upload` object is usually created by calling [[Collection::createUpload()]].
 *
 * Note: instance of this class is 'single use' only. Do not attempt to use same `Upload` instance for
 * multiple file upload.
 *
 * Usage example:
 *
 * ```php
 * $document = Yew::$app->mongodb->getFileCollection()->createUpload()
 *     ->addContent('Part 1')
 *     ->addContent('Part 2')
 *     // ...
 *     ->complete();
 * ```
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.1
 */
class Upload extends BaseObject
{
    /**
     * @var Collection file collection to be used.
     */
    public Collection $collection;
    /**
     * @var string filename to be used for file storage.
     */
    public string $filename;
    /**
     * @var array additional file document contents.
     * Common GridFS columns:
     *
     * - metadata: array, additional data associated with the file.
     * - aliases: array, an array of aliases.
     * - contentType: string, content type to be stored with the file.
     */
    public array $document = [];
    /**
     * @var int chunk size in bytes.
     */
    public int $chunkSize = 261120;
    /**
     * @var int total upload length in bytes.
     */
    public int $length = 0;
    /**
     * @var int file chunk counts.
     */
    public int $chunkCount = 0;

    /**
     * @var ObjectID file document ID.
     */
    private ObjectID $_documentId;
    /**
     * @var resource has context for collecting md5 hash
     */
    private $_hashContext;
    /**
     * @var string internal data buffer
     */
    private string $_buffer;
    /**
     * @var bool indicates whether upload is complete or not.
     */
    private bool $_isComplete = false;


    /**
     * Destructor.
     * Makes sure abandoned upload is cancelled.
     */
    public function __destruct()
    {
        if (!$this->_isComplete) {
            $this->cancel();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        $this->_hashContext = hash_init('md5');

        if (isset($this->document['_id'])) {
            if ($this->document['_id'] instanceof ObjectID) {
                $this->_documentId = $this->document['_id'];
            } else {
                try {
                    $this->_documentId = new ObjectID($this->document['_id']);
                } catch (InvalidArgumentException $e) {
                    // invalid id format
                    $this->_documentId = $this->document['_id'];
                }
            }
        } else {
            $this->_documentId = new ObjectID();
        }

        $this->collection->ensureIndexes();
    }

    /**
     * Adds string content to the upload.
     * This method can invoked several times before [[complete()]] is called.
     * @param string $content binary content.
     * @return $this self reference.
     */
    public function addContent(string $content): static
    {
        $freeBufferLength = $this->chunkSize - StringHelper::byteLength($this->_buffer);
        $contentLength = StringHelper::byteLength($content);
        if ($contentLength > $freeBufferLength) {
            $this->_buffer .= StringHelper::byteSubstr($content, 0, $freeBufferLength);
            $this->flushBuffer(true);
            return $this->addContent(StringHelper::byteSubstr($content, $freeBufferLength));
        } else {
            $this->_buffer .= $content;
            $this->flushBuffer();
        }

        return $this;
    }

    /**
     * Adds stream content to the upload.
     * This method can invoked several times before [[complete()]] is called.
     * @param resource $stream data source stream.
     * @return $this self reference.
     */
    public function addStream($stream): static
    {
        while (!feof($stream)) {
            $freeBufferLength = $this->chunkSize - StringHelper::byteLength($this->_buffer);

            $streamChunk = fread($stream, $freeBufferLength);
            if ($streamChunk === false) {
                break;
            }
            $this->_buffer .= $streamChunk;
            $this->flushBuffer();
        }

        return $this;
    }

    /**
     * Adds a file content to the upload.
     * This method can invoked several times before [[complete()]] is called.
     * @param string $filename source file name.
     * @return $this self reference.
     */
    public function addFile(string $filename): static
    {
        if ($this->filename === null) {
            $this->filename = basename($filename);
        }

        $stream = fopen($filename, 'r');
        if ($stream === false) {
            throw new InvalidParamException("Unable to read file '{$filename}'");
        }
        return $this->addStream($stream);
    }

    /**
     * Completes upload.
     * @return array saved document.
     */
    public function complete(): array
    {
        $this->flushBuffer(true);

        $document = $this->insertFile();

        $this->_isComplete = true;

        return $document;
    }

    /**
     * Cancels the upload.
     */
    public function cancel(): void
    {
        $this->_buffer = null;

        $this->collection->getChunkCollection()->remove(['files_id' => $this->_documentId], ['limit' => 0]);
        $this->collection->remove(['_id' => $this->_documentId], ['limit' => 1]);

        $this->_isComplete = true;
    }

    /**
     * Flushes [[buffer]] to the chunk if it is full.
     * @param bool $force whether to enforce flushing.
     */
    private function flushBuffer(bool $force = false): void
    {
        if ($this->_buffer === null) {
            return;
        }

        if ($force || StringHelper::byteLength($this->_buffer) == $this->chunkSize) {
            $this->insertChunk($this->_buffer);
            $this->_buffer = null;
        }
    }

    /**
     * Inserts file chunk.
     * @param string $data chunk binary content.
     */
    private function insertChunk(string $data): void
    {
        $chunkDocument = [
            'files_id' => $this->_documentId,
            'n' => $this->chunkCount,
            'data' => new Binary($data, Binary::TYPE_GENERIC),
        ];

        hash_update($this->_hashContext, $data);

        $this->collection->getChunkCollection()->insert($chunkDocument);
        $this->length += StringHelper::byteLength($data);
        $this->chunkCount++;
    }

    /**
     * Inserts [[document]] into file collection.
     * @return array inserted file document data.
     */
    private function insertFile(): array
    {
        $fileDocument = [
            '_id' => $this->_documentId,
            'uploadDate' => new UTCDateTime(),
        ];
        if ($this->filename === null) {
            $fileDocument['filename'] = $this->_documentId . '.dat';
        } else {
            $fileDocument['filename'] = $this->filename;
        }

        $fileDocument = array_merge(
            $fileDocument,
            $this->document,
            [
                'chunkSize' => $this->chunkSize,
                'length' => $this->length,
                'md5' => hash_final($this->_hashContext),
            ]
        );

        $this->collection->insert($fileDocument);
        return $fileDocument;
    }
}
