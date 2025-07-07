<?php
// Test file untuk auto-fix
class TestAutoFix {
	public function testFunction() {
		$array = array( 'item1', 'item2', 'item3' );

		$result = '';

		foreach ( $array as $item ) {
			$result .= $item . ' ';
		}

		return trim( $result );
	}
}
