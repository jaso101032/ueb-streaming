import "@fontsource-variable/inter";
import React from "react";
import { createRoot } from "react-dom/client";
import ErrorBoundary from "./platform/ErrorBoundary";
import Shell from "./platform/Shell";
import { Context } from "./platform/shared";
import AdminStudio from "./components/AdminStudio";
import ScheduledStudio from "./components/ScheduledStudio";
import Catalog from "./platform/Catalog";
import { EventList, EventDetail, Streaming } from "./platform/Management";
import EventForm from "./platform/EventForm";
import Auth, { Profile } from "./platform/Auth";
import Watch from "./platform/Watch";
import Channel from "./platform/Channel";
import UploadError from "./platform/UploadError";
import WatermarkSettings from "./platform/WatermarkSettings";
import UserManagement from "./platform/UserManagement";
import MediaLibrary from "./platform/MediaLibrary";
import AuditLog from "./platform/AuditLog";
const pages = {
    AuditLog,
    MediaLibrary,
    UserManagement,
    WatermarkSettings,
    UploadError,
    Channel,
    AdminStudio,
    ScheduledStudio,
    Catalog,
    EventList,
    EventDetail,
    Streaming,
    EventForm,
    Auth,
    Profile,
    Watch,
};
const root = document.getElementById("uebtv-platform");
if (root) {
    const mount = root.querySelector(
        "[data-react-page],#uebtv-admin-studio,#uebtv-scheduled-studio",
    );
    if (mount) {
        const name =
            mount.dataset.reactPage ||
            (mount.id === "uebtv-admin-studio"
                ? "AdminStudio"
                : "ScheduledStudio");
        const Page = pages[name],
            props = JSON.parse(mount.dataset.props),
            shell = JSON.parse(root.dataset.shell);
        const content = React.createElement(
            ErrorBoundary,
            null,
            React.createElement(Page, props),
        );
        createRoot(root).render(
            name === "Auth" || name === "UploadError"
                ? React.createElement(
                      Context.Provider,
                      { value: shell },
                      content,
                  )
                : React.createElement(Shell, { shell }, content),
        );
    }
}
