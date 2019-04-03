// @flow

import GraphQLRequestHelper from "../utils/GraphQLRequestHelper";
import _ from "lodash";
import {DEFAULT_NETWORK_ERROR} from "../constants/errors";
import QuestionParser from "../utils/QuestionParser";
import type {TaskSubmission} from "../types/Task";

type FetchTaskSubmissionDataArgument = { uuid: string, token: string };
type FetchTaskSubmissionDataReturn = { siteTitle: string, taskSubmission: TaskSubmission };

export default class TaskForBusinessOwnerDataService {

  static async fetchTaskSubmissionData(argument: FetchTaskSubmissionDataArgument): Promise<FetchTaskSubmissionDataReturn> {

    const {uuid, token} = {...argument};

    const query = `
query {
  readSiteConfig {
    Title
  }
  readTaskSubmission(UUID: "${uuid}", SecureToken: "${token}") {
    ID
    UUID
    TaskName
    Status
    Result
    QuestionnaireSubmission {
      UUID
    }
    QuestionnaireData
    AnswerData
  }
}`;
    const responseJSONObject = await GraphQLRequestHelper.request({query});
    const submissionJSONObject = _.get(responseJSONObject, "data.readTaskSubmission.0", {});
    if (!submissionJSONObject) {
      throw DEFAULT_NETWORK_ERROR;
    }

    const data: FetchTaskSubmissionDataReturn = {
      siteTitle: _.toString(_.get(responseJSONObject, "data.readSiteConfig.0.Title", "")),
      taskSubmission: {
        id: _.toString(_.get(submissionJSONObject, "ID", "")),
        uuid: _.toString(_.get(submissionJSONObject, "UUID", "")),
        taskName: _.toString(_.get(submissionJSONObject, "TaskName", "")),
        status: _.toString(_.get(submissionJSONObject, "Status", "")),
        result: _.toString(_.get(submissionJSONObject, "Result", "")),
        questionnaireSubmissionUUID: _.toString(_.get(submissionJSONObject, "QuestionnaireSubmission.UUID", "")),
        questions: QuestionParser.parseQuestionsFromJSON({
          schemaJSON: _.toString(_.get(submissionJSONObject, "QuestionnaireData", "")),
          answersJSON: _.toString(_.get(submissionJSONObject, "AnswerData", "")),
        }),
      },
    };

    return data;
  }
}