<?php

namespace Tomazt\OpenswooleMemory;

class SampleClass
{
	public function getSample(): array
	{
		return gc_status();
	}
}
