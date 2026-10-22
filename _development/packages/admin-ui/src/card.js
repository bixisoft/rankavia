/**
 * A titled surface. Layout only.
 *
 * @param {Object} props
 * @param {*}      props.title       Heading.
 * @param {*}      props.description Text under the heading.
 * @param {*}      props.footer      Footer content.
 * @param {*}      props.children    Body content.
 */
export function Card( { title, description, footer, children } ) {
	return (
		<section className="bx-card">
			{ ( title || description ) && (
				<header className="bx-card__header">
					{ title && <h2 className="bx-card__title">{ title }</h2> }
					{ description && (
						<p className="bx-card__description">{ description }</p>
					) }
				</header>
			) }
			<div className="bx-card__body">{ children }</div>
			{ footer && (
				<footer className="bx-card__footer">{ footer }</footer>
			) }
		</section>
	);
}
