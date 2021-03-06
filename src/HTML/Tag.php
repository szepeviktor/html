<?php
declare(strict_types=1);

namespace ItalyStrap\HTML;

/**
 * Class Tag
 *
 * @TODO Qualche idea da sviluppare in futuro
 * https://gitlab.com/byjoby/html-object-strings/blob/master/src/TagTrait.php
 *
 * @package ItalyStrap\HTML
 */
class Tag implements TagInterface {

	public static $is_debug = false;

	/**
	 * Array with tags indexed by $context.
	 *
	 * @var array
	 */
	private $tags = [];

	/**
	 * @var Attributes
	 */
	private $attr;

	/**
	 * Tag constructor.
	 * @param Attributes $attributes
	 */
	public function __construct( Attributes $attributes ) {
		$this->attr = $attributes;
	}

	/**
	 * @inheritDoc
	 */
	public function open( string $context, string $tag, array $attr = [], $is_void = false ): string {

		try {

			$this->setTag( $context, $tag );

		} catch ( \RuntimeException $e ) {
			echo $e->getMessage();
		} catch ( \Exception $e ) {
			echo $e->getMessage();
		}

		$this->attr->add( $context, $attr );

		if ( ! $tag = $this->getTag( $context ) ) {
			return '';
		}

		$self_close = '';

		if ( $is_void ) {
			$self_close = '/';
		}

		$output = \sprintf(
			'<%s%s%s>',
			esc_attr( $tag ),
			$this->attr->render( $context ),
			$self_close
		);

		if ( $this->is_debug() ) {
			return $this->add_comment_in_debug_mode( __FUNCTION__, $context, $output );
		}

		return $output;
	}

	/**
	 * @inheritDoc
	 */
	public function close( string $context ): string {

		if ( ! $tag = $this->getTag( $context ) ) {
			$this->removeTag( $context );
			return '';
		}

		$output = \sprintf(
			'</%s>',
			esc_attr( $tag )
		);

		if ( $this->is_debug() ) {
			$output = $this->add_comment_in_debug_mode( __FUNCTION__, $context, $output, 'post' );
		}

		$this->removeTag( $context );
		return $output;
	}

	/**
	 * @inheritDoc
	 */
	public function void( string $context, string $tag, array $attr = [] ): string {
		$output = $this->open( $context, $tag, $attr, true );
		$this->removeTag( $context );
		return $output;
	}

	/**
	 * @param string $context
	 * @param string $tag
	 * @param array $attr
	 * @param string $content
	 * @return string
	 * @example :
	 * <div>Some content</div>
	 * <i class="fa fa-icon"></i>
	 * Some content
	 *
	 * @todo Maybe future development
	 *
	 */
	private function element( string $context, string $tag, array $attr, string $content = '' ): string {

		/**
		 * @todo Può essere utile un fitro qui?
		 */
		$content = (string) \apply_filters( "italystrap_{$context}_element_content", $content, $context, $this );

		/**
		 * It could be used to display the content without the wrapper
		 */
		if ( (bool) \apply_filters( "italystrap_pre_{$context}", false, $context, $this ) ) {
			return $content;
		}

		$output = $this->open( $context, $tag, $attr ) . $content . $this->close( $context );

		return (string) \apply_filters( "italystrap_{$context}_element_output", $output, $context, $this );
	}

	/**
	 * @param string $context
	 * @param string $tag
	 * @return Tag
	 */
	private function setTag( string $context, string $tag ): Tag {

		if ( $this->hasTag( $context ) ) {
			throw new \RuntimeException( sprintf( 'The %s is already used', $context ) );
		}

		$this->tags[ $context ] = (string) \apply_filters( "italystrap_{$context}_tag", $tag, $context, $this );
		return $this;
	}

	/**
	 * @param string $context
	 * @return bool
	 */
	private function hasTag( string $context ): bool {
		return \array_key_exists( $context, $this->tags );
	}

	/**
	 * @param string $context
	 * @return string
	 */
	private function getTag( string $context ): string {

		if ( ! $this->hasTag( $context ) ) {
			return '';
		}

		return $this->tags[ $context ];
	}

	/**
	 * @param string $context
	 * @return $this
	 */
	private function removeTag( string $context ): Tag {
		unset( $this->tags[ $context] );
		return $this;
	}

	/**
	 * @param string $context
	 * @param string $new_tag
	 * @return $this
	 */
	private function changeTag( string $context, string $new_tag ): Tag {
		$this->tags[ $context] = $new_tag;
		return $this;
	}

	/**
	 * @todo Maybe make a both for selfclose
	 *
	 * @param string $func_name
	 * @param string $context
	 * @param string $html
	 * @param string $preOrPost
	 * @return string
	 */
	private function add_comment_in_debug_mode( string $func_name, string $context, string $html, string $preOrPost = 'pre' ) : string {

		$format = [
			'pre'	=> '<!-- %1$s in context: %2$s -->%3$s',
			'post'	=> '%3$s<!-- %1$s in context: %2$s -->',
			'both'	=> '<!-- %1$s in context: %2$s -->%3$s<!-- Self Close in context: %2$s -->',
		];

		return \sprintf(
			$format[ $preOrPost ],
			$func_name,
			esc_attr( $context ),
			$html
		);
	}

	/**
	 * @return bool
	 */
	private function is_debug() : bool {
		return self::$is_debug;
	}

	/**
	 * @throws \Exception
	 */
	private function check_non_closed_tags() {

		try {
			if ( \count( $this->tags ) > 0 ) {
				throw new \RuntimeException( \sprintf(
					'You forgot to close this tags: { %s }',
					\join( ' | ', $this->get_missed_close_tags() )
				) );
			}
		} catch ( \RuntimeException $e ) {
			echo $e->getMessage();
//			throw $e;
		} catch ( \Exception $e ) {
			echo $e->getMessage();
//			throw $e;
		}
	}

	/**
	 * @return array
	 */
	private function get_missed_close_tags() : array {
		$output = [];
		foreach ( $this->tags as $context => $tag ) {
			$output[] = sprintf(
				'Context "%s": Tag "%s"',
				$context,
				$tag
			);
		}

		return $output;
	}

	/**
	 * @throws \Exception
	 */
	public function __destruct() {
		$this->check_non_closed_tags();
	}
}