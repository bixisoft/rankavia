/**
 * Page heading, optional actions on the right, then the content. Layout only.
 *
 * @param {Object} props
 * @param {*}      props.title       Page heading.
 * @param {*}      props.description Text under the heading.
 * @param {*}      props.actions     Controls shown at the right of the heading.
 * @param {*}      props.children    Page content.
 */
export function Page( { title, description, actions, children } ) {
	return (
		<div className="bx-page">
			<div className="bx-page__header">
				<div className="bx-page__heading">
					<h2 className="bx-page__title">{ title }</h2>
					{ description && (
						<p className="bx-page__description">{ description }</p>
					) }
				</div>
				{ actions && (
					<div className="bx-page__actions">{ actions }</div>
				) }
			</div>
			{ children }
		</div>
	);
}
