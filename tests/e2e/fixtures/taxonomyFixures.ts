import { Locator } from '@playwright/test';
import { NodeCreationFormPOM } from '../pages/nodeCreation/NodeCreationFormPOM';

export interface TaxonomyFixures {
  name: string;
  term: string;
  field: (basicPage: NodeCreationFormPOM) => Locator;
  select: (basicPage: NodeCreationFormPOM) => Promise<void>;
  remove: (basicPage: NodeCreationFormPOM) => Promise<void>;
}

const categoryTerm = process.env.PLAYWRIGHT_E2E_CATEGORY_TERM ?? 'Animated shorts';
const prisonOwnerTerm = process.env.PLAYWRIGHT_E2E_PRISON_OWNER_TERM ?? 'Bedford';
const topicTerm = process.env.PLAYWRIGHT_E2E_TOPIC_TERM ?? 'Appeals';
const seriesTerm = process.env.PLAYWRIGHT_E2E_SERIES_TERM ?? categoryTerm;

export const taxonomyFixtures: TaxonomyFixures[] = [
  {
    name: 'Category',
    term: categoryTerm,
    field: (basicPage) => basicPage.categoryField(),
    select: (basicPage) => basicPage.selectFirstCategory(categoryTerm),
    remove: (basicPage) => basicPage.selectFirstCategoryRemoveButton(),
  },
  {
    name: 'Prison owner',
    term: prisonOwnerTerm,
    field: (basicPage) => basicPage.prisonOwnerField(),
    select: (basicPage) => basicPage.selectPrisonOwner(prisonOwnerTerm),
    remove: (basicPage) => basicPage.selectPrisonOwnerRemoveButton(),
  },
  {
    name: 'Topic',
    term: topicTerm,
    field: (basicPage) => basicPage.topicField(),
    select: (basicPage) => basicPage.selectTopic(topicTerm),
    remove: (basicPage) => basicPage.selectTopicRemoveButton(),
  },
  {
    name: 'Series',
    term: seriesTerm,
    field: (basicPage) => basicPage.seriesField(),
    select: (basicPage) => basicPage.selectSeries(seriesTerm),
    remove: (basicPage) => basicPage.selectSeriesRemoveButton(),
  },
];