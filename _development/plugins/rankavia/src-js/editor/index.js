import { registerPlugin } from '@wordpress/plugins';
import {
	PluginDocumentSettingPanel,
	PluginSidebar,
	PluginSidebarMoreMenuItem,
} from '@wordpress/editor';
import { __ } from '@wordpress/i18n';
import { search } from '@wordpress/icons';
import { SidebarPanels, SummaryPanel, useSeoModel } from './sidebar';
import './style.css';

const SIDEBAR = 'rankavia';

function Rankavia() {
	const model = useSeoModel();

	return (
		<>
			<PluginSidebarMoreMenuItem target={ SIDEBAR } icon={ search }>
				{ __( 'Rankavia', 'rankavia' ) }
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				name={ SIDEBAR }
				title={ __( 'Rankavia', 'rankavia' ) }
				icon={ search }
			>
				<SidebarPanels model={ model } />
			</PluginSidebar>
			<PluginDocumentSettingPanel
				name="rankavia-summary"
				title={ __( 'Rankavia', 'rankavia' ) }
			>
				<SummaryPanel model={ model } />
			</PluginDocumentSettingPanel>
		</>
	);
}

registerPlugin( 'rankavia', { render: Rankavia, icon: search } );
