<?php

namespace Symfony\UX\TwigComponent;

/**
 * @author Radu Sebastian <seby@acron.ro>
 *
 * @experimental
 */
abstract class AbstractComponent implements ComponentInterface
{
	public static function getTemplate(): ?string
	{
		return null;
	}
}