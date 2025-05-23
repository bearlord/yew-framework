<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Server\Beans\Http;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
/**
 * Trait implementing functionality common to requests and responses.
 */
trait MessageTrait
{

    /**
     * @var array 存放headers的小写name
     */
    protected array $headerNames = [];

    /**
     * @var array
     */
    protected array $headers = [];

    /**
     * @var string
     */
    protected string $protocol = '1.1';

    /**
     * @var StreamInterface|null
     */
    protected ?StreamInterface $stream = null;

    /**
     * Retrieves the HTTP protocol version as a string.
     * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
     *
     * @return string HTTP protocol version.
     */
    public function getProtocolVersion(): string
    {
        return $this->protocol;
    }

    /**
     * Return an instance with the specified HTTP protocol version.
     * The version string MUST contain only the HTTP version number (e.g.,
     * "1.1", "1.0").
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new protocol version.
     *
     * @param string $version HTTP protocol version
     * @return $this
     */
    public function withProtocolVersion(string $version): self
    {
        if ($this->protocol === $version) {
            return $this;
        }

        $this->protocol = $version;
        return $this;
    }

    /**
     * Retrieves all message header values.
     * The keys represent the header name as it will be sent over the wire, and
     * each value is an array of strings associated with the header.
     *     // Represent the headers as a string
     *     foreach ($message->getHeaders() as $name => $values) {
     *         echo $name . ": " . implode(", ", $values);
     *     }
     *     // Emit headers iteratively:
     *     foreach ($message->getHeaders() as $name => $values) {
     *         foreach ($values as $value) {
     *             header(sprintf('%s: %s', $name, $value), false);
     *         }
     *     }
     * While header names are not case-sensitive, getHeaders() will preserve the
     * exact case in which headers were originally specified.
     *
     * @return string[][] Returns an associative array of the message's headers. Each
     *     key MUST be a header name, and each value MUST be an array of strings
     *     for that header.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     *
     * @param string $name Case-insensitive header field name.
     * @return bool Returns true if any header names match the given header
     *                     name using a case-insensitive string comparison. Returns false if
     *                     no matching header name is found in the message.
     */
    public function hasHeader(string $name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    /**
     * Retrieves a message header value by the given case-insensitive name.
     * This method returns an array of all the header values of the given
     * case-insensitive header name.
     * If the header does not appear in the message, this method MUST return an
     * empty array.
     *
     * @param string $name Case-insensitive header field name.
     * @return string[] An array of string values as provided for the given
     *                     header. If the header does not appear in the message, this method MUST
     *                     return an empty array.
     */
    public function getHeader(string $name): array
    {
        $name = strtolower($name);

        if (!isset($this->headerNames[$name])) {
            return [];
        }

        $name = $this->headerNames[$name];

        return $this->headers[$name];
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     * This method returns all the header values of the given
     * case-insensitive header name as a string concatenated together using
     * a comma.
     * NOTE: Not all header values may be appropriately represented using
     * comma concatenation. For such headers, use getHeader() instead
     * and supply your own delimiter when concatenating.
     * If the header does not appear in the message, this method MUST return
     * an empty string.
     *
     * @param string $name Case-insensitive header field name.
     * @return string A string of values as provided for the given header
     *                     concatenated together using a comma. If the header does not appear in
     *                     the message, this method MUST return an empty string.
     */
    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    /**
     * Return an instance with the provided value replacing the specified header.
     * While header names are case-insensitive, the casing of the header will
     * be preserved by this function, and returned from getHeaders().
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new and/or updated header and value.
     *
     * @param string $name Case-insensitive header field name.
     * @param string|string[] $value Header value(s).
     * @return $this
     * @throws InvalidArgumentException for invalid header names or values.
     */
    public function withHeader(string $name, $value)
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        $value = $this->trimHeaderValues($value);
        $normalized = strtolower($name);


        if (isset($this->headerNames[$normalized])) {
            unset($this->headers[$this->headerNames[$normalized]]);
        }
        $this->headerNames[$normalized] = $name;
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * @param array $headers
     * @return $this
     */
    public function withHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->withHeader(str_replace('_', '-', $name), $value);
        }
        return $this;
    }

    /**
     * Return an instance with the specified header appended with the given value.
     * Existing values for the specified header will be maintained. The new
     * value(s) will be appended to the existing list. If the header did not
     * exist previously, it will be added.
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new header and/or value.
     *
     * @param string $name Case-insensitive header field name to add.
     * @param string|string[] $value Header value(s).
     * @return $this
     * @throws InvalidArgumentException for invalid header names or values.
     */
    public function withAddedHeader(string $name, $value): self
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        $value = $this->trimHeaderValues($value);
        $normalized = strtolower($name);

        if (isset($this->headerNames[$normalized])) {
            $this->headerNames[$normalized] = $name;
            $this->headers[$name] = array_merge($this->headers[$name], $value);
        } else {
            $this->headerNames[$normalized] = $name;
            $this->headers[$name] = $value;
        }

        return $this;
    }

    /**
     * Return an instance without the specified header.
     * Header resolution MUST be done without case-sensitivity.
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that removes
     * the named header.
     *
     * @param string $name Case-insensitive header field name to remove.
     * @return $this
     */
    public function withoutHeader(string $name): self
    {
        $normalized = strtolower($name);

        if (!isset($this->headerNames[$normalized])) {
            return $this;
        }

        $name = $this->headerNames[$normalized];

        unset($this->headers[$name], $this->headerNames[$normalized]);

        return $this;
    }

    /**
     * @param array $headers
     * @return $this
     */
    public function setHeaders(array $headers)
    {
        $this->headerNames = $this->headers = [];
        foreach ($headers as $header => $value) {
            if (!is_array($value)) {
                $value = [$value];
            }

            $value = $this->trimHeaderValues($value);

            $normalized = strtolower($header);
            if (isset($this->headerNames[$normalized])) {
                $header = $this->headerNames[$normalized];
                $this->headers[$header] = array_merge($this->headers[$header], $value);
            } else {
                $this->headerNames[$normalized] = $header;
                $this->headers[$header] = $value;
            }
        }
        return $this;
    }

    /**
     * Return an instance with the specified message body.
     *
     * The body MUST be a StreamInterface object.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return a new instance that has the
     * new body stream.
     *
     * @param StreamInterface $body Body.
     * @return $this
     * @throws InvalidArgumentException When the body is not valid.
     */
    public function withBody(StreamInterface $body): self
    {
        if ($body === $this->stream) {
            return $this;
        }

        $this->stream = $body;
        return $this;
    }

    /**
     * Gets the body of the message.
     *
     * @return StreamInterface Returns the body
     */
    public function getBody() : StreamInterface
    {
        if (!$this->stream) {
            $this->stream = new HttpStream('');
        }

        return $this->stream;
    }


    /**
     * Trims whitespace from the header values.
     * Spaces and tabs ought to be excluded by parsers when extracting the field value from a header field.
     * header-field = field-name ":" OWS field-value OWS
     * OWS          = *( SP / HTAB )
     *
     * @param string[] $values Header values
     * @return string[] Trimmed header values
     * @see https://tools.ietf.org/html/rfc7230#section-3.2.4
     */
    private function trimHeaderValues(array $values): array
    {
        return array_map(function ($value) {
            return trim($value, " \t");
        }, $values);
    }

    /**
     * @return string
     */
    public function getContentType(): string
    {
        return $this->getHeaderLine('Content-Type');
    }
}
