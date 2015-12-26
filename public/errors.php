<?php
use Psr\Http\Message\MessageInterface as MessageInterface;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * @param Response $response
 * @return MessageInterface
 */
function forbidden(Response $response)
{
    $response->getBody()->write(json_encode(array("code" => 403, "message" => "Forbidden")));
    return $response
        ->withStatus(403)
        ->withHeader('Content-Type', 'application/json');
}

/**
 * @param Response $response
 * @return MessageInterface
 */
function badRequest(Response $response)
{
    $response->getBody()->write(json_encode(array("code" => 400, "message" => "Bad request")));
    return $response
        ->withStatus(400)
        ->withHeader('Content-Type', 'application/json');
}

/**
 * @param Response $response
 * @return MessageInterface
 */
function notFound(Response $response)
{
    $response->getBody()->write(json_encode(array("code" => 404, "message" => "Not Found")));
    return $response
        ->withStatus(404)
        ->withHeader('Content-Type', 'application/json');
}

/**
 * @param Response $response
 * @return MessageInterface
 */
function conflict(Response $response)
{
    $response->getBody()->write(json_encode(array("code" => 409, "message" => "Conflict")));
    return $response
        ->withStatus(409)
        ->withHeader('Content-Type', 'application/json');
}

/**
 * @param Response $response
 * @return MessageInterface
 */
function internalError(Response $response)
{
    $response->getBody()->write(json_encode(array("code" => 500, "message" => "Internal error")));
    return $response
        ->withStatus(500)
        ->withHeader('Content-Type', 'application/json');
}

/**
 * Handle an unexpected error
 *
 * @param Response $response
 * @return MessageInterface
 */
function handleError(Response $response)
{
   internalError($response);
}