/**
 * EGroupware AmirRAG - Client-side TypeScript
 *
 * @package amirrag
 * @link https://www.egroupware.org
 * @author Amir
 * @copyright 2025 by Amir
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

import {EgwApp} from '../../api/js/jsapi/egw_app';

/**
 * UI for AmirRAG application
 */
export class AmirRAGApp extends EgwApp {
	
	/**
	 * Constructor
	 */
	constructor() {
		super('amirrag');
	}
	
	/**
	 * Destructor
	 */
	destroy() {
		super.destroy();
	}
	
	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.
	 *
	 * @param et2 newly ready et2 object
	 * @param name template name
	 */
	et2_ready(et2, name: string) {
		super.et2_ready(et2, name);
		
		// Initialize UI elements based on template
		if (name === 'amirrag.index') {
			this.initSearchUI();
		} else if (name === 'amirrag.indexdata') {
			this.initIndexDataUI();
		}
	}
	
	/**
	 * Initialize the search UI
	 */
	private initSearchUI() {
		// Toggle AI response section visibility based on checkbox
		const useLlmCheckbox = this.et2?.getWidgetById('use_llm');
		const aiResponseSection = this.et2?.getWidgetById('ai_response_section');
		
		if (useLlmCheckbox && aiResponseSection) {
			// Initially hide AI response section if checkbox is unchecked
			this.toggleAiResponseSection(useLlmCheckbox.getValue());
			
			// Listen for checkbox changes
			useLlmCheckbox.getDOMNode()?.addEventListener('sl-change', () => {
				this.toggleAiResponseSection(useLlmCheckbox.getValue());
			});
		}
		
		// Add keyboard shortcut for search (Enter key)
		const searchInput = this.et2?.getWidgetById('search_query');
		if (searchInput) {
			searchInput.getDOMNode()?.addEventListener('keydown', (e: KeyboardEvent) => {
				if (e.key === 'Enter' && !e.shiftKey) {
					e.preventDefault();
					this.triggerSearch();
				}
			});
		}
		
		// Hide empty state when results are present
		this.updateResultsVisibility();
	}
	
	/**
	 * Initialize the index data UI
	 */
	private initIndexDataUI() {
		// Add visual feedback for indexing operations
		const indexButton = this.et2?.getWidgetById('index_action');
		if (indexButton) {
			indexButton.getDOMNode()?.addEventListener('click', () => {
				this.updateIndexingStatus('Indexing...');
			});
		}
	}
	
	/**
	 * Toggle AI response section visibility
	 */
	private toggleAiResponseSection(show: boolean) {
		const aiResponseSection = this.et2?.getWidgetById('ai_response_section');
		if (aiResponseSection) {
			const domNode = aiResponseSection.getDOMNode();
			if (domNode) {
				domNode.style.display = show ? 'block' : 'none';
			}
		}
	}
	
	/**
	 * Trigger the search action
	 */
	private triggerSearch() {
		const searchButton = this.et2?.getWidgetById('button[search]');
		if (searchButton) {
			searchButton.getDOMNode()?.click();
		}
	}
	
	/**
	 * Update results visibility (show/hide empty state)
	 */
	private updateResultsVisibility() {
		const resultsGrid = this.et2?.getWidgetById('results_grid');
		const emptyState = this.et2?.getWidgetById('empty_state');
		
		if (resultsGrid && emptyState) {
			// Check if there are results (more than just header row)
			const hasResults = resultsGrid.getDOMNode()?.querySelectorAll('.row').length > 0;
			
			const gridDom = resultsGrid.getDOMNode();
			const emptyDom = emptyState.getDOMNode();
			
			if (gridDom && emptyDom) {
				gridDom.style.display = hasResults ? 'table' : 'none';
				emptyDom.style.display = hasResults ? 'none' : 'flex';
			}
		}
	}
	
	/**
	 * Update indexing status indicator
	 */
	private updateIndexingStatus(status: string) {
		const statusElement = this.et2?.getWidgetById('indexing_status');
		if (statusElement) {
			statusElement.set_value(status);
		}
	}
	
	/**
	 * Format relevance score with color coding
	 */
	public formatRelevanceScore(score: number): string {
		const percentage = Math.round(score * 100);
		let colorClass = 'amirrag-relevance-low';
		
		if (percentage >= 80) {
			colorClass = 'amirrag-relevance-high';
		} else if (percentage >= 50) {
			colorClass = 'amirrag-relevance-medium';
		}
		
		return `<span class="${colorClass}">${percentage}%</span>`;
	}
}

// Make the app available globally
app.classes.amirrag = AmirRAGApp;
